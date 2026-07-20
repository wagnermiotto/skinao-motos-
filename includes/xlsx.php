<?php
/**
 * Gerador mínimo de arquivos .xlsx (Excel) — sem dependências externas.
 * Monta o container ZIP manualmente (método "store"), então funciona em qualquer hospedagem,
 * mesmo sem a extensão zip do PHP.
 *
 * Uso:
 *   $bin = xlsx_gerar($colunas, $linhas, $totais);
 *   header(...); echo $bin;
 *
 *   $colunas = [['t'=>'Título','w'=>18,'tipo'=>'texto'|'dinheiro'|'numero'], ...]
 *   $linhas  = [[valor, valor, ...], ...]  (alinhado às colunas; floats p/ dinheiro/numero)
 *   $totais  = [['label'=>'Total vendido','valor'=>1234.0,'tipo'=>'dinheiro'], ...]
 */

function xlsx_col_letra(int $n): string
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1, 26);
    }
    return $s;
}

function xlsx_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_gerar(array $colunas, array $linhas, array $totais = [], string $abaNome = 'Vendas'): string
{
    // estilos: 0 default | 1 cabeçalho (negrito branco/vermelho) | 2 dinheiro | 3 rótulo negrito
    $estiloDinheiro = 2;

    $rows = '';

    // cabeçalho
    $rows .= '<row r="1">';
    foreach ($colunas as $i => $c) {
        $ref = xlsx_col_letra($i + 1) . '1';
        $rows .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $c['t']) . '</t></is></c>';
    }
    $rows .= '</row>';
    $r = 2;

    // dados
    foreach ($linhas as $linha) {
        $rows .= '<row r="' . $r . '">';
        foreach ($colunas as $i => $c) {
            $ref = xlsx_col_letra($i + 1) . $r;
            $tipo = $c['tipo'] ?? 'texto';
            $val = $linha[$i] ?? '';
            if ($tipo === 'dinheiro' || $tipo === 'numero') {
                $num = is_numeric($val) ? 0 + $val : 0;
                $s = $tipo === 'dinheiro' ? ' s="' . $estiloDinheiro . '"' : '';
                $rows .= '<c r="' . $ref . '"' . $s . '><v>' . $num . '</v></c>';
            } else {
                $rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $val) . '</t></is></c>';
            }
        }
        $rows .= '</row>';
        $r++;
    }

    // linha em branco + totais
    if ($totais) {
        $r++;
        foreach ($totais as $t) {
            $rows .= '<row r="' . $r . '">';
            $rows .= '<c r="A' . $r . '" s="3" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $t['label']) . '</t></is></c>';
            $tipo = $t['tipo'] ?? 'texto';
            if ($tipo === 'dinheiro' || $tipo === 'numero') {
                $num = is_numeric($t['valor']) ? 0 + $t['valor'] : 0;
                $s = $tipo === 'dinheiro' ? ' s="' . $estiloDinheiro . '"' : '';
                $rows .= '<c r="B' . $r . '"' . $s . '><v>' . $num . '</v></c>';
            } else {
                $rows .= '<c r="B' . $r . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $t['valor']) . '</t></is></c>';
            }
            $rows .= '</row>';
            $r++;
        }
    }

    // larguras das colunas
    $cols = '<cols>';
    foreach ($colunas as $i => $c) {
        $w = $c['w'] ?? 14;
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $cols .= '</cols>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . $cols
        . '<sheetData>' . $rows . '</sheetData>'
        . '</worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;R$&quot;\ #,##0.00"/></numFmts>'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDC2626"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_esc($abaNome) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    return xlsx_zip([
        '[Content_Types].xml'          => $contentTypes,
        '_rels/.rels'                  => $rels,
        'xl/workbook.xml'              => $workbook,
        'xl/_rels/workbook.xml.rels'   => $workbookRels,
        'xl/styles.xml'                => $styles,
        'xl/worksheets/sheet1.xml'     => $sheet,
    ]);
}

/**
 * Monta um arquivo ZIP (método "store", sem compressão) a partir de [nome => conteúdo].
 */
function xlsx_zip(array $arquivos): string
{
    $local = '';
    $central = '';
    $offset = 0;
    foreach ($arquivos as $nome => $dados) {
        $crc = crc32($dados);
        $len = strlen($dados);
        $nomeLen = strlen($nome);
        $lfh = "PK\x03\x04" . pack('v', 20) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', $len) . pack('V', $len) . pack('v', $nomeLen) . pack('v', 0) . $nome . $dados;
        $local .= $lfh;
        $central .= "PK\x01\x02" . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', $len) . pack('V', $len) . pack('v', $nomeLen)
            . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset) . $nome;
        $offset += strlen($lfh);
    }
    $n = count($arquivos);
    $eocd = "PK\x05\x06" . pack('v', 0) . pack('v', 0) . pack('v', $n) . pack('v', $n)
        . pack('V', strlen($central)) . pack('V', $offset) . pack('v', 0);
    return $local . $central . $eocd;
}
