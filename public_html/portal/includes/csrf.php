<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valido(string|null $tokenRecebido): bool
{
    if ($tokenRecebido === null || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $tokenRecebido);
}

function exigir_csrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_valido(is_string($token) ? $token : null)) {
        http_response_code(419);
        exit('CSRF inválido. Recarregue a página e tente novamente.');
    }
}

