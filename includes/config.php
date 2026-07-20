<?php
/**
 * Configuração do site — Skinão Motos.
 *
 * NÃO edite este arquivo na hospedagem. Para publicar em uma hospedagem
 * compartilhada (cPanel, Hostinger, HostGator, etc.):
 *
 * 1. Renomeie "includes/config.local.php.example" para "includes/config.local.php".
 * 2. Preencha ali os dados do banco MySQL criado no painel da hospedagem.
 * 3. Importe sql/schema.mysql.sql (e sql/dados-producao.sql) pelo phpMyAdmin.
 *
 * O config.local.php tem prioridade sobre os valores padrão abaixo, então
 * atualizações futuras deste arquivo nunca apagam as credenciais de produção.
 *
 * Sem config.local.php, o site roda em SQLite, sozinho e sem configurar nada
 * (usado para testar no computador local).
 */

// Sobrescritas locais/de produção (não versionado). Precisa vir ANTES dos define() padrão.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_DRIVER') || define('DB_DRIVER', 'sqlite'); // 'sqlite' (local/teste) ou 'mysql' (hospedagem)

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'skinao_motos');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

defined('SQLITE_PATH') || define('SQLITE_PATH', __DIR__ . '/../data/dev.sqlite');

defined('SITE_NAME') || define('SITE_NAME', 'Skinão Motos');
defined('UPLOADS_DIR') || define('UPLOADS_DIR', __DIR__ . '/../uploads/motos');
defined('UPLOADS_URL') || define('UPLOADS_URL', '/uploads/motos');
defined('UPLOADS_CLIENTES_DIR') || define('UPLOADS_CLIENTES_DIR', __DIR__ . '/../uploads/clientes');
defined('UPLOADS_CLIENTES_URL') || define('UPLOADS_CLIENTES_URL', '/uploads/clientes');
defined('UPLOADS_RH_DIR') || define('UPLOADS_RH_DIR', __DIR__ . '/../uploads/rh');
defined('UPLOADS_RH_URL') || define('UPLOADS_RH_URL', '/uploads/rh');

defined('WHATSAPP_NUMBER') || define('WHATSAPP_NUMBER', '5516997155789'); // DDI+DDD+numero, só dígitos
defined('CONTACT_PHONE') || define('CONTACT_PHONE', '(16) 3262-5789');
defined('CONTACT_HELIO') || define('CONTACT_HELIO', 'Hélio (16) 99787-6170');
defined('CONTACT_LEILANE') || define('CONTACT_LEILANE', 'Leilane (16) 99715-5789');
defined('CONTACT_ADDRESS') || define('CONTACT_ADDRESS', 'R. João Dias Miranda, 140 - Itauera Res., Itápolis - SP, 14900-000');
defined('CONTACT_HOURS_WEEKDAY') || define('CONTACT_HOURS_WEEKDAY', 'Seg a Sex: 08h às 18h'); // atualize com o horário real da loja
defined('CONTACT_HOURS_WEEKEND') || define('CONTACT_HOURS_WEEKEND', 'Sáb: 08h às 12h');
defined('INSTAGRAM_URL') || define('INSTAGRAM_URL', ''); // link do Instagram da loja — vazio oculta o ícone

session_start();
