<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/contratos.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$fmt = $_GET['fmt'] ?? 'view';

$stmt = db()->prepare('SELECT * FROM contratos WHERE id = ?');
$stmt->execute([$id]);
$contrato = $stmt->fetch();
if (!$contrato) {
    http_response_code(404);
    die('Contrato não encontrado.');
}

$corpo = $contrato['corpo_html'] ?: '';
$numero = $contrato['numero'];
$nomeArquivo = 'contrato-' . strtolower($numero);

/* ---------------- estilos do documento (compartilhados com o Word) ---------------- */
$docCss = <<<CSS
  .ct-doc{font-family:"Calibri","Segoe UI",Arial,sans-serif;font-size:11pt;line-height:1.4;color:#000;text-align:justify;}
  .ct-doc p{margin:0 0 8pt;}
  .ct-titulo{text-align:center;font-size:15pt;font-weight:700;margin:0 0 16pt;}
  .ct-label{margin:0 0 2pt;}
  .ct-parte{margin:0 0 10pt;}
  .ct-intro{margin:10pt 0;}
  .ct-clausula{font-size:11.5pt;font-weight:700;margin:14pt 0 6pt;text-align:left;}
  .ct-obj{margin-left:6pt;line-height:1.55;}
  .ct-preco{font-weight:600;}
  .ct-extenso{font-weight:400;font-style:italic;}
  .ct-pgto{margin:2pt 0;}
  .ct-sub{margin:10pt 0 2pt;}
  .ct-obs{white-space:pre-wrap;}
  .ct-fecho{margin-top:14pt;}
  .ct-data{margin:24pt 0 20pt;text-align:left;}
  .ct-assinaturas{width:100%;margin-top:18pt;}
  .ct-assina{text-align:center;padding:14pt 20pt;}
  .ct-assina .ct-linha{border-top:1px solid #000;margin:0 auto 4pt;width:82%;}
  .ct-assina p{margin:0;font-size:10.5pt;line-height:1.35;}
CSS;

/* ---------------- exportação Word (.doc via HTML, abre idêntico no Word) ---------------- */
if ($fmt === 'word') {
    // no Word, grade CSS não é confiável — assinaturas viram tabela 2x2
    $corpoWord = preg_replace(
        '/<div class="ct-assinaturas">(.*?)<\/div>\s*<\/div>/s',
        '$1</div>',
        $corpo
    );
    // transforma os 4 blocos .ct-assina numa tabela 2x2 para o Word
    if (preg_match_all('/<div class="ct-assina">(.*?)<\/div>/s', $corpo, $mm) && count($mm[1]) === 4) {
        $b = $mm[1];
        $tabela = '<table class="ct-assinaturas" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
            . '<tr><td class="ct-assina" width="50%">' . $b[0] . '</td><td class="ct-assina" width="50%">' . $b[1] . '</td></tr>'
            . '<tr><td class="ct-assina" width="50%">' . $b[2] . '</td><td class="ct-assina" width="50%">' . $b[3] . '</td></tr>'
            . '</table>';
        $corpoWord = preg_replace('/<div class="ct-assinaturas">.*?<\/div>\s*(?=<\/div>\s*$)/s', $tabela, $corpo);
    } else {
        $corpoWord = $corpo;
    }

    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '.doc"');
    header('Cache-Control: no-cache, must-revalidate');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><title>' . e($numero) . '</title>';
    echo '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->';
    echo '<style>@page{size:A4;margin:2cm 1.8cm;} body{margin:0;}' . $docCss . '</style></head>';
    echo '<body>' . $corpoWord . '</body></html>';
    exit;
}

/* ---------------- visualização em tela (impressão / PDF) ---------------- */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contrato <?= e($numero) ?></title>
<link rel="icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/contrato.css">
</head>
<body class="ct-view-body">

<div class="ct-toolbar">
  <span class="ct-num">Contrato <?= e($numero) ?>
    <?php if ($contrato['status'] === 'cancelado'): ?>
      <span style="color:#ff6b6b">(CANCELADO)</span>
    <?php endif; ?>
  </span>
  <button class="ct-btn ct-btn--primary" onclick="window.print()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
    Imprimir / Salvar PDF
  </button>
  <a class="ct-btn" href="contrato-ver.php?id=<?= (int) $id ?>&fmt=word">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>
    Baixar Word
  </a>
  <a class="ct-btn" href="contrato-gerar.php?id=<?= (int) $id ?>">✏️ Editar</a>
  <a class="ct-btn" href="contratos.php">← Contratos</a>
</div>

<div class="ct-paper"><?= $corpo ?></div>

<?php if (!empty($_GET['novo'])): ?>
<script>
  // abre o diálogo de impressão automaticamente ao gerar um contrato novo
  window.addEventListener('load', function () { setTimeout(function(){ /* pronto para imprimir */ }, 400); });
</script>
<?php endif; ?>
</body>
</html>
