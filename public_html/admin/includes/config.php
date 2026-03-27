<?php
declare(strict_types=1);

$configSistema = require dirname(__DIR__, 3) . '/config/config.php';

date_default_timezone_set($configSistema['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!defined('APP_NOME')) {
    define('APP_NOME', $configSistema['app_nome']);
    define('APP_URL', $configSistema['app_url']);
    define('DB_HOST', $configSistema['db_host']);
    define('DB_PORT', $configSistema['db_port']);
    define('DB_NOME', $configSistema['db_nome']);
    define('DB_USUARIO', $configSistema['db_usuario']);
    define('DB_SENHA', $configSistema['db_senha']);
    define('DB_CHARSET', $configSistema['db_charset']);
    define('LOGIN_MAX_TENTATIVAS', $configSistema['login_max_tentativas']);
    define('LOGIN_BLOQUEIO_MINUTOS', $configSistema['login_bloqueio_minutos']);
}

if (!function_exists('e')) {
    /**
     * Escape seguro para saída HTML.
     */
    function e(string|null $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('definir_flash')) {
    function definir_flash(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'] = [
            'tipo' => $tipo,
            'mensagem' => $mensagem,
        ];
    }
}

if (!function_exists('consumir_flash')) {
    function consumir_flash(): array|null
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
}

if (!function_exists('redirecionar')) {
    function redirecionar(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('obter_ip_cliente')) {
    function obter_ip_cliente(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

