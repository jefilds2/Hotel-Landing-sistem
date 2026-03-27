<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes.php';

function registrar_erro_auth_bd(string $contexto, Throwable $erro): void
{
    error_log("[BD][AUTH][{$contexto}] " . $erro->getMessage());
}

function usuarios_tem_coluna_email(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'email'");
        $cache = (bool)$stmt->fetch();
        return $cache;
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('usuarios_tem_coluna_email', $erro);
        return false;
    }
}

function usuario_logado(): bool
{
    return !empty($_SESSION['usuario']['id']);
}

function usuario_atual(): array|null
{
    return $_SESSION['usuario'] ?? null;
}

function usuario_nivel_atual(): string
{
    return (string)($_SESSION['usuario']['nivel'] ?? '');
}

function usuario_eh_master(): bool
{
    return usuario_nivel_atual() === 'master';
}

function usuario_eh_admin_ou_master(): bool
{
    return in_array(usuario_nivel_atual(), ['master', 'admin'], true);
}

function exigir_login(): void
{
    if (!usuario_logado()) {
        definir_flash('erro', 'Faça login para acessar o painel.');
        redirecionar('/admin/login.php');
    }
}

function exigir_admin(): void
{
    exigir_login();
    if (!usuario_eh_admin_ou_master()) {
        http_response_code(403);
        exit('Acesso restrito ao perfil administrador.');
    }
}

function exigir_master(): void
{
    exigir_login();
    if (!usuario_eh_master()) {
        http_response_code(403);
        exit('Acesso restrito ao perfil master.');
    }
}

function login_esta_bloqueado(PDO $pdo, string $login, string $ip): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT tentativas, bloqueado_ate
            FROM login_tentativas
            WHERE login_nome = :login AND ip = :ip
            LIMIT 1
        ');
        $stmt->execute([
            'login' => $login,
            'ip' => $ip,
        ]);
        $registro = $stmt->fetch();

        if (!$registro || empty($registro['bloqueado_ate'])) {
            return ['bloqueado' => false, 'mensagem' => ''];
        }

        $agora = new DateTimeImmutable('now');
        $bloqueadoAte = new DateTimeImmutable($registro['bloqueado_ate']);

        if ($bloqueadoAte > $agora) {
            $minutos = (int)ceil(($bloqueadoAte->getTimestamp() - $agora->getTimestamp()) / 60);
            return [
                'bloqueado' => true,
                'mensagem' => "Login bloqueado temporariamente. Tente novamente em {$minutos} minuto(s).",
            ];
        }

        return [
            'bloqueado' => false,
            'mensagem' => '',
        ];
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('login_esta_bloqueado', $erro);
        return ['bloqueado' => false, 'mensagem' => ''];
    }
}

function registrar_tentativa_falha(PDO $pdo, string $login, string $ip): void
{
    try {
        $stmtBusca = $pdo->prepare('
            SELECT id, tentativas
            FROM login_tentativas
            WHERE login_nome = :login AND ip = :ip
            LIMIT 1
        ');
        $stmtBusca->execute([
            'login' => $login,
            'ip' => $ip,
        ]);
        $registro = $stmtBusca->fetch();

        if (!$registro) {
            $stmtInsert = $pdo->prepare('
                INSERT INTO login_tentativas (login_nome, ip, tentativas, bloqueado_ate)
                VALUES (:login, :ip, 1, NULL)
            ');
            $stmtInsert->execute([
                'login' => $login,
                'ip' => $ip,
            ]);
            return;
        }

        $tentativasAtualizadas = (int)$registro['tentativas'] + 1;
        $bloqueadoAte = null;

        if ($tentativasAtualizadas >= LOGIN_MAX_TENTATIVAS) {
            $bloqueadoAte = (new DateTimeImmutable('now'))
                ->modify('+' . LOGIN_BLOQUEIO_MINUTOS . ' minutes')
                ->format('Y-m-d H:i:s');
        }

        $stmtUpdate = $pdo->prepare('
            UPDATE login_tentativas
            SET tentativas = :tentativas, bloqueado_ate = :bloqueado_ate
            WHERE id = :id
        ');
        $stmtUpdate->execute([
            'tentativas' => $tentativasAtualizadas,
            'bloqueado_ate' => $bloqueadoAte,
            'id' => $registro['id'],
        ]);
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('registrar_tentativa_falha', $erro);
    }
}

function limpar_tentativas_login(PDO $pdo, string $login, string $ip): void
{
    try {
        $stmt = $pdo->prepare('
            DELETE FROM login_tentativas
            WHERE login_nome = :login AND ip = :ip
        ');
        $stmt->execute([
            'login' => $login,
            'ip' => $ip,
        ]);
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('limpar_tentativas_login', $erro);
    }
}

function autenticar_usuario(string $email, string $senha): array
{
    try {
        $email = strtolower(trim($email));
        $ip = obter_ip_cliente();
        $pdo = obter_conexao();

        if ($email === '' || trim($senha) === '') {
            return ['ok' => false, 'mensagem' => 'Informe email e senha.'];
        }

        $statusBloqueio = login_esta_bloqueado($pdo, $email, $ip);
        if ($statusBloqueio['bloqueado']) {
            registrar_log($pdo, 'login_bloqueado', "Login bloqueado para {$email}");
            return ['ok' => false, 'mensagem' => $statusBloqueio['mensagem']];
        }

        $temEmail = usuarios_tem_coluna_email($pdo);
        if ($temEmail) {
            $stmt = $pdo->prepare('
                SELECT id, nome, email, usuario, senha_hash, nivel
                FROM usuarios
                WHERE email = :email AND ativo = 1
                LIMIT 1
            ');
            $stmt->execute(['email' => $email]);
        } else {
            $usuarioCompat = $email;
            if (str_contains($usuarioCompat, '@')) {
                $usuarioCompat = explode('@', $usuarioCompat, 2)[0];
            }
            $stmt = $pdo->prepare('
                SELECT id, nome, NULL AS email, usuario, senha_hash, nivel
                FROM usuarios
                WHERE usuario = :usuario AND ativo = 1
                LIMIT 1
            ');
            $stmt->execute(['usuario' => $usuarioCompat]);
        }
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            registrar_tentativa_falha($pdo, $email, $ip);
            registrar_log($pdo, 'login_falha', "Falha de autenticação para {$email}");
            return ['ok' => false, 'mensagem' => 'Credenciais inválidas.'];
        }

        limpar_tentativas_login($pdo, $email, $ip);
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id' => (int)$usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'] ?? '',
            'usuario' => $usuario['usuario'],
            'nivel' => $usuario['nivel'],
        ];

        registrar_log($pdo, 'login_sucesso', 'Usuário autenticado com sucesso');

        return ['ok' => true, 'mensagem' => 'Login efetuado com sucesso.'];
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('autenticar_usuario', $erro);
        return ['ok' => false, 'mensagem' => 'Falha de comunicação com o banco. Tente novamente em instantes.'];
    }
}

function logout_usuario(): void
{
    if (!usuario_logado()) {
        return;
    }

    try {
        $pdo = obter_conexao();
        registrar_log($pdo, 'logout', 'Usuário encerrou a sessão');
    } catch (Throwable $erro) {
        registrar_erro_auth_bd('logout_usuario', $erro);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
