CREATE TABLE IF NOT EXISTS motos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    marca TEXT NOT NULL,
    modelo TEXT NOT NULL,
    ano INTEGER,
    preco DECIMAL(10,2) NOT NULL,
    categoria TEXT NOT NULL DEFAULT 'outra',
    km INTEGER DEFAULT 0,
    cor TEXT,
    condicao TEXT NOT NULL DEFAULT 'seminovo',
    descricao TEXT,
    destaque INTEGER NOT NULL DEFAULT 0,
    ativo INTEGER NOT NULL DEFAULT 1,
    valor_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
    gasto_manutencao DECIMAL(10,2) NOT NULL DEFAULT 0,
    gasto_documentacao DECIMAL(10,2) NOT NULL DEFAULT 0,
    gasto_transporte DECIMAL(10,2) NOT NULL DEFAULT 0,
    outros_custos DECIMAL(10,2) NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'disponivel',
    vendido_em DATETIME,
    cliente_nome TEXT NOT NULL DEFAULT '',
    cliente_documento TEXT NOT NULL DEFAULT '',
    cliente_telefone TEXT NOT NULL DEFAULT '',
    cliente_cidade TEXT NOT NULL DEFAULT '',
    cliente_estado TEXT NOT NULL DEFAULT '',
    vendedor TEXT NOT NULL DEFAULT '',
    placa TEXT NOT NULL DEFAULT '',
    chassi TEXT NOT NULL DEFAULT '',
    forma_pagamento TEXT NOT NULL DEFAULT '',
    venda_obs TEXT,
    numero_venda TEXT NOT NULL DEFAULT '',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS moto_fotos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    moto_id INTEGER NOT NULL,
    arquivo TEXT NOT NULL,
    ordem INTEGER NOT NULL DEFAULT 0,
    capa INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (moto_id) REFERENCES motos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    arquivo TEXT NOT NULL,
    ordem INTEGER NOT NULL DEFAULT 0,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
