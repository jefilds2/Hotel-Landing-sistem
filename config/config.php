<?php
declare(strict_types=1);

/**
 * Carrega o arquivo .env em formato simples (CHAVE=VALOR).
 */
function carregar_env_arquivo(string $caminho): array
{
    if (!is_file($caminho)) {
        return [];
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) {
        return [];
    }

    $env = [];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        if (
            (str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }

        $env[$chave] = $valor;
    }

    return $env;
}

$raizProjeto = dirname(__DIR__);
$env = carregar_env_arquivo($raizProjeto . '/.env');

return [
    'app_nome' => $env['APP_NOME'] ?? 'Hotel Bela Vista',
    'app_url' => $env['APP_URL'] ?? 'http://localhost',
    'timezone' => $env['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    'db_host' => $env['DB_HOST'] ?? '127.0.0.1',
    'db_port' => $env['DB_PORT'] ?? '3306',
    'db_nome' => $env['DB_NOME'] ?? 'hotel_bela_vista',
    'db_usuario' => $env['DB_USUARIO'] ?? 'hotel_user',
    'db_senha' => $env['DB_SENHA'] ?? 'hotel_pass_123',
    'db_charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
    'login_max_tentativas' => (int)($env['LOGIN_MAX_TENTATIVAS'] ?? 5),
    'login_bloqueio_minutos' => (int)($env['LOGIN_BLOQUEIO_MINUTOS'] ?? 15),
];

