<?php
/**
 * Ponto de entrada do painel: acessar /admin ou /admin/ cai aqui e é
 * encaminhado para o login (ou direto para o painel, se já estiver logado).
 */
require_once __DIR__ . '/../includes/auth.php';

if (current_admin_id()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
