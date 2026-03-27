<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('/portal/index.php');
}

exigir_csrf();

logout_usuario();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
definir_flash('info', 'Sessão encerrada com segurança.');
redirecionar('/portal/login.php');
