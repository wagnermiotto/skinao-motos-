<?php
/**
 * Módulo RH — constantes e funções de apoio (funcionários, documentos, férias, salários).
 */

require_once __DIR__ . '/helpers.php';

const RH_STATUS = [
    'ativo'     => 'Ativo',
    'ferias'    => 'Férias',
    'afastado'  => 'Afastado',
    'desligado' => 'Desligado',
];

const RH_DOC_TIPOS = [
    'RG', 'CPF', 'Carteira de Trabalho', 'Contrato', 'Exame', 'Certificado', 'Outro',
];

const RH_SALARIO_TIPOS = [
    'salario_base' => 'Salário base',
    'aumento'      => 'Aumento',
    'bonificacao'  => 'Bonificação',
    'desconto'     => 'Desconto',
    'comissao'     => 'Comissão',
];

const RH_FERIAS_STATUS = [
    'agendada'     => 'Agendada',
    'em_andamento' => 'Em andamento',
    'concluida'    => 'Concluída',
];

const RH_GENEROS = ['Masculino', 'Feminino', 'Outro'];
const RH_ESTADOS_CIVIS = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Viúvo(a)', 'União estável'];

function rh_status_label(string $s): string
{
    return RH_STATUS[$s] ?? $s;
}

function rh_salario_tipo_label(string $s): string
{
    return RH_SALARIO_TIPOS[$s] ?? $s;
}

function rh_ferias_status_label(string $s): string
{
    return RH_FERIAS_STATUS[$s] ?? $s;
}

function rh_funcionario(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM rh_funcionarios WHERE id = ?');
    $stmt->execute([$id]);
    $f = $stmt->fetch();
    return $f ?: null;
}

/** Lista de funcionários para seleção de vendedor (não desligados). */
function rh_vendedores(): array
{
    try {
        return db()->query(
            "SELECT id, nome, cpf, comissao_percent, cargo FROM rh_funcionarios
             WHERE status <> 'desligado' ORDER BY nome"
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Busca um funcionário pelo nome (case-insensitive, ignorando espaços extras). */
function rh_funcionario_por_nome(string $nome): ?array
{
    $nome = trim($nome);
    if ($nome === '') {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM rh_funcionarios WHERE LOWER(nome) = LOWER(?) LIMIT 1');
        $stmt->execute([$nome]);
        $f = $stmt->fetch();
        return $f ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function rh_foto_url(string $arquivo): string
{
    return UPLOADS_RH_URL . '/' . $arquivo;
}

function rh_doc_url(int $funcionarioId, string $arquivo): string
{
    return UPLOADS_RH_URL . '/' . $funcionarioId . '/' . $arquivo;
}

function rh_iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    if (!$partes || $partes[0] === '') {
        return '?';
    }
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr($partes[count($partes) - 1], 0, 1);
    }
    return mb_strtoupper($ini);
}

/** Idade a partir da data de nascimento (ou null). */
function rh_idade(?string $nascimento): ?int
{
    if (empty($nascimento)) {
        return null;
    }
    $n = date_create($nascimento);
    if (!$n) {
        return null;
    }
    return (int) $n->diff(date_create('today'))->y;
}

/** Tempo de casa em anos/meses (texto), a partir da data de admissão. */
function rh_tempo_casa(?string $admissao): string
{
    if (empty($admissao)) {
        return '—';
    }
    $a = date_create($admissao);
    if (!$a) {
        return '—';
    }
    $d = $a->diff(date_create('today'));
    $partes = [];
    if ($d->y > 0) { $partes[] = $d->y . ' ano' . ($d->y > 1 ? 's' : ''); }
    if ($d->m > 0) { $partes[] = $d->m . ($d->m > 1 ? ' meses' : ' mês'); }
    if (!$partes) { $partes[] = $d->d . ' dia' . ($d->d != 1 ? 's' : ''); }
    return implode(' e ', $partes);
}

/**
 * Salva a foto do funcionário em UPLOADS_RH_DIR/{id}/ com nome aleatório.
 * Retorna "{id}/{arquivo}" (relativo à pasta rh) ou null se não houver upload.
 */
function salvar_upload_rh_foto(array $file, int $funcionarioId): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da foto (código ' . $file['error'] . ').');
    }
    if ($file['size'] > 6 * 1024 * 1024) {
        throw new RuntimeException('Foto maior que 6MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('Formato de foto não suportado. Use JPG, PNG ou WEBP.');
    }
    $dir = UPLOADS_RH_DIR . '/' . $funcionarioId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $nome = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $nome);
    return $funcionarioId . '/' . $nome;
}

/**
 * Salva um documento (imagem ou PDF) em UPLOADS_RH_DIR/{id}/.
 * Retorna o nome do arquivo salvo (sem o prefixo do id) ou null se não houver upload.
 */
function salvar_upload_rh_doc(array $file, int $funcionarioId): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio do documento (código ' . $file['error'] . ').');
    }
    if ($file['size'] > 12 * 1024 * 1024) {
        throw new RuntimeException('Documento maior que 12MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ][$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('Formato não suportado. Use PDF, JPG, PNG ou WEBP.');
    }
    $dir = UPLOADS_RH_DIR . '/' . $funcionarioId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $nome = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . '/' . $nome);
    return $nome;
}
