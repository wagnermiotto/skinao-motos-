<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } else {
        $isNew = !file_exists(SQLITE_PATH);
        $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($isNew) {
            $schema = file_get_contents(__DIR__ . '/../sql/schema.sqlite.sql');
            $pdo->exec($schema);
        }
    }

    ensure_extra_tables($pdo);
    ensure_moto_financeiro_columns($pdo);
    ensure_contratos_tables($pdo);
    ensure_rh_tables($pdo);

    return $pdo;
}

/**
 * Cria as tabelas do módulo RH (funcionários, documentos, férias e histórico salarial).
 * Idempotente (CREATE ... IF NOT EXISTS). Não altera nenhuma tabela existente.
 */
function ensure_rh_tables(PDO $pdo): void
{
    if (DB_DRIVER === 'mysql') {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_funcionarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(160) NOT NULL,
                foto VARCHAR(255) NOT NULL DEFAULT '',
                cargo VARCHAR(120) NOT NULL DEFAULT '',
                departamento VARCHAR(120) NOT NULL DEFAULT '',
                cpf VARCHAR(20) NOT NULL DEFAULT '',
                rg VARCHAR(30) NOT NULL DEFAULT '',
                pis VARCHAR(30) NOT NULL DEFAULT '',
                data_nascimento DATE NULL,
                genero VARCHAR(20) NOT NULL DEFAULT '',
                estado_civil VARCHAR(30) NOT NULL DEFAULT '',
                data_admissao DATE NULL,
                data_demissao DATE NULL,
                salario DECIMAL(10,2) NOT NULL DEFAULT 0,
                comissao_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
                telefone VARCHAR(30) NOT NULL DEFAULT '',
                email VARCHAR(160) NOT NULL DEFAULT '',
                endereco VARCHAR(200) NOT NULL DEFAULT '',
                numero VARCHAR(20) NOT NULL DEFAULT '',
                bairro VARCHAR(120) NOT NULL DEFAULT '',
                cidade VARCHAR(120) NOT NULL DEFAULT '',
                estado VARCHAR(2) NOT NULL DEFAULT '',
                cep VARCHAR(12) NOT NULL DEFAULT '',
                banco VARCHAR(80) NOT NULL DEFAULT '',
                agencia VARCHAR(20) NOT NULL DEFAULT '',
                conta VARCHAR(30) NOT NULL DEFAULT '',
                chave_pix VARCHAR(140) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'ativo',
                observacoes TEXT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_documentos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                tipo VARCHAR(60) NOT NULL DEFAULT 'Outro',
                titulo VARCHAR(160) NOT NULL DEFAULT '',
                arquivo VARCHAR(255) NOT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_ferias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                data_inicio DATE NULL,
                data_fim DATE NULL,
                dias INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'agendada',
                observacoes TEXT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_salario_historico (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                tipo VARCHAR(30) NOT NULL DEFAULT 'salario_base',
                valor DECIMAL(10,2) NOT NULL DEFAULT 0,
                data_referencia DATE NULL,
                descricao VARCHAR(200) NOT NULL DEFAULT '',
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_funcionarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                foto TEXT NOT NULL DEFAULT '',
                cargo TEXT NOT NULL DEFAULT '',
                departamento TEXT NOT NULL DEFAULT '',
                cpf TEXT NOT NULL DEFAULT '',
                rg TEXT NOT NULL DEFAULT '',
                pis TEXT NOT NULL DEFAULT '',
                data_nascimento DATE,
                genero TEXT NOT NULL DEFAULT '',
                estado_civil TEXT NOT NULL DEFAULT '',
                data_admissao DATE,
                data_demissao DATE,
                salario DECIMAL(10,2) NOT NULL DEFAULT 0,
                comissao_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
                telefone TEXT NOT NULL DEFAULT '',
                email TEXT NOT NULL DEFAULT '',
                endereco TEXT NOT NULL DEFAULT '',
                numero TEXT NOT NULL DEFAULT '',
                bairro TEXT NOT NULL DEFAULT '',
                cidade TEXT NOT NULL DEFAULT '',
                estado TEXT NOT NULL DEFAULT '',
                cep TEXT NOT NULL DEFAULT '',
                banco TEXT NOT NULL DEFAULT '',
                agencia TEXT NOT NULL DEFAULT '',
                conta TEXT NOT NULL DEFAULT '',
                chave_pix TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'ativo',
                observacoes TEXT,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_documentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                funcionario_id INTEGER NOT NULL,
                tipo TEXT NOT NULL DEFAULT 'Outro',
                titulo TEXT NOT NULL DEFAULT '',
                arquivo TEXT NOT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_ferias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                funcionario_id INTEGER NOT NULL,
                data_inicio DATE,
                data_fim DATE,
                dias INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'agendada',
                observacoes TEXT,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS rh_salario_historico (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                funcionario_id INTEGER NOT NULL,
                tipo TEXT NOT NULL DEFAULT 'salario_base',
                valor DECIMAL(10,2) NOT NULL DEFAULT 0,
                data_referencia DATE,
                descricao TEXT NOT NULL DEFAULT '',
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }
}

/**
 * Adiciona à tabela motos as colunas de controle financeiro/venda que podem não
 * existir em bancos criados antes dessa funcionalidade. Idempotente: só cria o que falta,
 * preservando todos os dados existentes.
 */
function ensure_moto_financeiro_columns(PDO $pdo): void
{
    $mysql = DB_DRIVER === 'mysql';
    $txt = fn(int $n) => $mysql ? "VARCHAR($n) NOT NULL DEFAULT ''" : "TEXT NOT NULL DEFAULT ''";

    $colunas = [
        'valor_compra'       => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'valor_venda'        => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'gasto_manutencao'   => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'gasto_documentacao' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'gasto_transporte'   => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'outros_custos'      => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'status'             => $mysql ? "VARCHAR(20) NOT NULL DEFAULT 'disponivel'" : "TEXT NOT NULL DEFAULT 'disponivel'",
        'vendido_em'         => $mysql ? 'DATETIME NULL' : 'DATETIME',
        // ----- dados da venda (para a exportação/fechamento) -----
        'cliente_nome'       => $txt(120),
        'cliente_documento'  => $txt(20),
        'cliente_telefone'   => $txt(30),
        'cliente_cidade'     => $txt(80),
        'cliente_estado'     => $txt(2),
        'vendedor'           => $txt(120),
        'placa'              => $txt(10),
        'chassi'             => $txt(30),
        'forma_pagamento'    => $txt(40),
        'venda_obs'          => 'TEXT',
        'numero_venda'       => $txt(20),
    ];

    $existentes = [];
    if (DB_DRIVER === 'mysql') {
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'motos'"
        );
        foreach ($stmt->fetchAll() as $r) {
            $existentes[strtolower($r['COLUMN_NAME'])] = true;
        }
    } else {
        foreach ($pdo->query('PRAGMA table_info(motos)')->fetchAll() as $r) {
            $existentes[strtolower($r['name'])] = true;
        }
    }

    foreach ($colunas as $coluna => $definicao) {
        if (!isset($existentes[$coluna])) {
            $pdo->exec("ALTER TABLE motos ADD COLUMN {$coluna} {$definicao}");
        }
    }
}

/**
 * Cria as tabelas do módulo Contratos (configurações, modelos e contratos emitidos).
 * Idempotente: CREATE ... IF NOT EXISTS + seed único da empresa e do modelo padrão.
 * Não altera nenhuma tabela existente.
 */
function ensure_contratos_tables(PDO $pdo): void
{
    $mysql = DB_DRIVER === 'mysql';

    if ($mysql) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS configuracoes (
                chave VARCHAR(80) PRIMARY KEY,
                valor TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS contrato_modelos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                corpo_html MEDIUMTEXT NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS contratos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero VARCHAR(30) NOT NULL UNIQUE,
                modelo_id INT NULL,
                moto_id INT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'emitido',
                dados MEDIUMTEXT NULL,
                corpo_html MEDIUMTEXT NULL,
                cliente_nome VARCHAR(160) NOT NULL DEFAULT '',
                moto_desc VARCHAR(200) NOT NULL DEFAULT '',
                placa VARCHAR(10) NOT NULL DEFAULT '',
                valor DECIMAL(10,2) NOT NULL DEFAULT 0,
                vendedor VARCHAR(120) NOT NULL DEFAULT '',
                forma_pagamento VARCHAR(60) NOT NULL DEFAULT '',
                data_contrato DATE NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                cancelado_em DATETIME NULL,
                cancelado_motivo TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS configuracoes (
                chave TEXT PRIMARY KEY,
                valor TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS contrato_modelos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                corpo_html TEXT NOT NULL,
                ativo INTEGER NOT NULL DEFAULT 1,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS contratos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                numero TEXT NOT NULL UNIQUE,
                modelo_id INTEGER,
                moto_id INTEGER,
                status TEXT NOT NULL DEFAULT 'emitido',
                dados TEXT,
                corpo_html TEXT,
                cliente_nome TEXT NOT NULL DEFAULT '',
                moto_desc TEXT NOT NULL DEFAULT '',
                placa TEXT NOT NULL DEFAULT '',
                valor DECIMAL(10,2) NOT NULL DEFAULT 0,
                vendedor TEXT NOT NULL DEFAULT '',
                forma_pagamento TEXT NOT NULL DEFAULT '',
                data_contrato DATE,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                cancelado_em DATETIME,
                cancelado_motivo TEXT
            )"
        );
    }

    // seed: dados da empresa (uma única vez) — retirados do contrato modelo, editáveis em Configurações
    $temConfig = (int) $pdo->query('SELECT COUNT(*) c FROM configuracoes')->fetch()['c'];
    if ($temConfig === 0) {
        $seed = [
            'empresa_razao'      => 'HELIO ARINI MOTOS LTDA',
            'empresa_fantasia'   => 'Skinão Motos',
            'empresa_cnpj'       => '16.806.595/0001-07',
            'empresa_ie'         => '',
            'empresa_endereco'   => 'Avenida Carlos Adolfson, 1637',
            'empresa_numero'     => '1',
            'empresa_bairro'     => 'Jardim Boa Vista',
            'empresa_cidade'     => 'Itápolis',
            'empresa_uf'         => 'SP',
            'empresa_cep'        => '',
            'empresa_telefones'  => '(16) 3262-5789',
            'empresa_email'      => '',
            'empresa_logo'       => '',
            'testemunha1_nome'   => '',
            'testemunha1_cpf'    => '',
            'testemunha2_nome'   => '',
            'testemunha2_cpf'    => '',
        ];
        $ins = $pdo->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?, ?)');
        foreach ($seed as $k => $v) {
            $ins->execute([$k, $v]);
        }
    }

    // seed: modelo padrão (uma única vez)
    $temModelo = (int) $pdo->query("SELECT COUNT(*) c FROM contrato_modelos WHERE slug = 'compra-venda-padrao'")->fetch()['c'];
    if ($temModelo === 0) {
        require_once __DIR__ . '/contratos.php';
        $pdo->prepare('INSERT INTO contrato_modelos (nome, slug, corpo_html, ativo) VALUES (?, ?, ?, 1)')
            ->execute(['Contrato de Compra e Venda (Padrão)', 'compra-venda-padrao', contrato_template_padrao()]);
    }
}

/**
 * Cria tabelas adicionais (ex.: clientes) que podem não existir em bancos
 * criados antes dessa funcionalidade. Idempotente e barato — CREATE ... IF NOT EXISTS.
 */
function ensure_extra_tables(PDO $pdo): void
{
    if (DB_DRIVER === 'mysql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                arquivo VARCHAR(255) NOT NULL,
                ordem INT NOT NULL DEFAULT 0,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                arquivo TEXT NOT NULL,
                ordem INTEGER NOT NULL DEFAULT 0,
                ativo INTEGER NOT NULL DEFAULT 1,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
