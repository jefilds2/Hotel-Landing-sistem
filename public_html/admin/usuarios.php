<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

exigir_master();
$pdo = obter_conexao();
$usuario = usuario_atual();

function validar_email_usuario(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um email válido.');
    }
    return $email;
}

function validar_senha_usuario(string $senha): string
{
    $senha = trim($senha);
    if (strlen($senha) < 8) {
        throw new InvalidArgumentException('A senha deve ter no mínimo 8 caracteres.');
    }
    return $senha;
}

function validar_nivel_usuario(string $nivel): string
{
    $nivel = trim($nivel);
    if (!in_array($nivel, ['master', 'admin'], true)) {
        throw new InvalidArgumentException('Cargo inválido.');
    }
    return $nivel;
}

function buscar_usuario_por_id(PDO $pdo, int $usuarioId): array|null
{
    try {
        $stmt = $pdo->prepare('SELECT id, nome, email, usuario, nivel, ativo, criado_em FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuarioId]);
        $item = $stmt->fetch();
        return $item ?: null;
    } catch (Throwable $erro) {
        registrar_erro_bd('buscar_usuario_por_id', $erro);
        return null;
    }
}

function total_masters_ativos(PDO $pdo): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios WHERE nivel = 'master' AND ativo = 1");
        return (int)($stmt->fetch()['total'] ?? 0);
    } catch (Throwable $erro) {
        registrar_erro_bd('total_masters_ativos', $erro);
        return 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_usuario') {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $email = validar_email_usuario((string)($_POST['email'] ?? ''));
            $usuarioLogin = trim((string)($_POST['usuario'] ?? ''));
            $senha = validar_senha_usuario((string)($_POST['senha'] ?? ''));
            $nivel = validar_nivel_usuario((string)($_POST['nivel'] ?? 'admin'));
            $ativo = ((string)($_POST['ativo'] ?? '1')) === '1' ? 1 : 0;

            if ($nome === '') {
                throw new InvalidArgumentException('Nome é obrigatório.');
            }

            $stmt = $pdo->prepare('
                INSERT INTO usuarios (nome, email, usuario, senha_hash, nivel, ativo)
                VALUES (:nome, :email, :usuario, :senha_hash, :nivel, :ativo)
            ');
            $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'usuario' => $usuarioLogin !== '' ? $usuarioLogin : null,
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                'nivel' => $nivel,
                'ativo' => $ativo,
            ]);

            $novoId = (int)$pdo->lastInsertId();
            registrar_log($pdo, 'criar_usuario', "Usuário {$novoId} criado no painel master");
            definir_flash('sucesso', 'Usuário criado com sucesso.');
            redirecionar('/admin/usuarios.php?editar=' . $novoId);
        }

        if ($acao === 'editar_usuario') {
            $usuarioId = (int)($_POST['usuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                throw new InvalidArgumentException('Usuário inválido para edição.');
            }

            $registroAtual = buscar_usuario_por_id($pdo, $usuarioId);
            if (!$registroAtual) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            $nome = trim((string)($_POST['nome'] ?? ''));
            $email = validar_email_usuario((string)($_POST['email'] ?? ''));
            $usuarioLogin = trim((string)($_POST['usuario'] ?? ''));
            $nivel = validar_nivel_usuario((string)($_POST['nivel'] ?? 'admin'));
            $ativo = ((string)($_POST['ativo'] ?? '1')) === '1' ? 1 : 0;

            if ($nome === '') {
                throw new InvalidArgumentException('Nome é obrigatório.');
            }

            $vaiPerderMasterAtivo = (
                $registroAtual['nivel'] === 'master'
                && (int)$registroAtual['ativo'] === 1
                && ($nivel !== 'master' || $ativo !== 1)
            );
            if ($vaiPerderMasterAtivo && total_masters_ativos($pdo) <= 1) {
                throw new RuntimeException('Não é possível remover o último usuário master ativo.');
            }

            $stmt = $pdo->prepare('
                UPDATE usuarios
                SET nome = :nome,
                    email = :email,
                    usuario = :usuario,
                    nivel = :nivel,
                    ativo = :ativo
                WHERE id = :id
            ');
            $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'usuario' => $usuarioLogin !== '' ? $usuarioLogin : null,
                'nivel' => $nivel,
                'ativo' => $ativo,
                'id' => $usuarioId,
            ]);

            if ((int)($usuario['id'] ?? 0) === $usuarioId) {
                $_SESSION['usuario']['nome'] = $nome;
                $_SESSION['usuario']['email'] = $email;
                $_SESSION['usuario']['usuario'] = $usuarioLogin;
                $_SESSION['usuario']['nivel'] = $nivel;
            }

            registrar_log($pdo, 'editar_usuario', "Usuário {$usuarioId} atualizado no painel master");
            definir_flash('sucesso', 'Usuário atualizado com sucesso.');
            if ((int)($usuario['id'] ?? 0) === $usuarioId && $nivel !== 'master') {
                redirecionar('/admin/index.php');
            }
            redirecionar('/admin/usuarios.php?editar=' . $usuarioId);
        }

        if ($acao === 'trocar_senha_usuario') {
            $usuarioId = (int)($_POST['usuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                throw new InvalidArgumentException('Usuário inválido para alteração de senha.');
            }

            $registroAtual = buscar_usuario_por_id($pdo, $usuarioId);
            if (!$registroAtual) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            $senhaNova = validar_senha_usuario((string)($_POST['nova_senha'] ?? ''));
            $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id');
            $stmt->execute([
                'senha_hash' => password_hash($senhaNova, PASSWORD_DEFAULT),
                'id' => $usuarioId,
            ]);

            registrar_log($pdo, 'trocar_senha_usuario', "Senha alterada para usuário {$usuarioId}");
            definir_flash('sucesso', 'Senha atualizada com sucesso.');
            redirecionar('/admin/usuarios.php?editar=' . $usuarioId);
        }

        if ($acao === 'excluir_usuario') {
            $usuarioId = (int)($_POST['usuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                throw new InvalidArgumentException('Usuário inválido para exclusão.');
            }
            if ((int)($usuario['id'] ?? 0) === $usuarioId) {
                throw new RuntimeException('Não é permitido excluir o usuário logado.');
            }

            $registroAtual = buscar_usuario_por_id($pdo, $usuarioId);
            if (!$registroAtual) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            if ($registroAtual['nivel'] === 'master' && (int)$registroAtual['ativo'] === 1 && total_masters_ativos($pdo) <= 1) {
                throw new RuntimeException('Não é possível excluir o último usuário master ativo.');
            }

            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $stmt->execute(['id' => $usuarioId]);

            registrar_log($pdo, 'excluir_usuario', "Usuário {$usuarioId} excluído no painel master");
            definir_flash('sucesso', 'Usuário excluído com sucesso.');
            redirecionar('/admin/usuarios.php');
        }

        throw new RuntimeException('Ação inválida.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            definir_flash('erro', 'Email ou usuário já cadastrado. Informe dados únicos.');
            redirecionar('/admin/usuarios.php');
        }
        definir_flash('erro', 'Erro ao salvar usuário.');
        redirecionar('/admin/usuarios.php');
    } catch (Throwable $e) {
        definir_flash('erro', $e->getMessage());
        redirecionar('/admin/usuarios.php');
    }
}

$flash = consumir_flash();
$busca = trim((string)($_GET['q'] ?? ''));
$editarId = (int)($_GET['editar'] ?? 0);
$usuarioEdicao = $editarId > 0 ? buscar_usuario_por_id($pdo, $editarId) : null;

$sqlUsuarios = '
    SELECT id, nome, email, usuario, nivel, ativo, criado_em
    FROM usuarios
    WHERE 1 = 1
';
$params = [];
if ($busca !== '') {
    $sqlUsuarios .= ' AND (nome LIKE :busca OR email LIKE :busca OR usuario LIKE :busca) ';
    $params['busca'] = '%' . $busca . '%';
}
$sqlUsuarios .= ' ORDER BY (nivel = \'master\') DESC, nome ASC LIMIT 300';

$usuarios = [];
try {
    $stmtUsuarios = $pdo->prepare($sqlUsuarios);
    $stmtUsuarios->execute($params);
    $usuarios = $stmtUsuarios->fetchAll();
} catch (Throwable $erro) {
    registrar_erro_bd('listar_usuarios_tela', $erro);
    if (!$flash) {
        $flash = ['tipo' => 'erro', 'mensagem' => 'Falha ao carregar usuários. Tente novamente em instantes.'];
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Gerenciar Usuários - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
  <main class="admin-page">
    <header class="topbar">
      <div>
        <h1>Gerenciamento de Usuários</h1>
        <small>Acesso exclusivo do cargo master</small>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-neutral" href="/admin/index.php">Dashboard</a>
        <a class="btn btn-neutral" href="/admin/pessoas.php">Cadastro de hóspedes</a>
        <a class="btn btn-neutral" href="/admin/historico.php">Histórico</a>
        <form method="post" action="/admin/logout.php" class="inline-form"><?= csrf_input() ?><button class="btn btn-primary" type="submit">Sair</button></form>
      </div>
    </header>

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['tipo']) ?>" data-flash><?= e($flash['mensagem']) ?></div><?php endif; ?>

    <section class="card">
      <h2>Novo usuário</h2>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="criar_usuario">
        <div class="grid-3">
          <div><label>Nome</label><input name="nome" required></div>
          <div><label>Email</label><input name="email" type="email" required></div>
          <div><label>Usuário (opcional)</label><input name="usuario"></div>
          <div><label>Senha inicial</label><input name="senha" type="password" minlength="8" required></div>
          <div>
            <label>Cargo</label>
            <select name="nivel">
              <option value="admin">Admin</option>
              <option value="master">Master</option>
            </select>
          </div>
          <div>
            <label>Status</label>
            <select name="ativo">
              <option value="1">Ativo</option>
              <option value="0">Inativo</option>
            </select>
          </div>
        </div>
        <div style="margin-top:10px;"><button class="btn btn-success" type="submit">Criar usuário</button></div>
      </form>
    </section>

    <?php if ($usuarioEdicao): ?>
      <section class="card">
        <h2>Editar usuário #<?= e((string)$usuarioEdicao['id']) ?></h2>
        <form method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="acao" value="editar_usuario">
          <input type="hidden" name="usuario_id" value="<?= e((string)$usuarioEdicao['id']) ?>">
          <div class="grid-3">
            <div><label>Nome</label><input name="nome" value="<?= e($usuarioEdicao['nome']) ?>" required></div>
            <div><label>Email</label><input name="email" type="email" value="<?= e($usuarioEdicao['email']) ?>" required></div>
            <div><label>Usuário (opcional)</label><input name="usuario" value="<?= e($usuarioEdicao['usuario']) ?>"></div>
            <div>
              <label>Cargo</label>
              <select name="nivel">
                <option value="admin" <?= $usuarioEdicao['nivel'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="master" <?= $usuarioEdicao['nivel'] === 'master' ? 'selected' : '' ?>>Master</option>
              </select>
            </div>
            <div>
              <label>Status</label>
              <select name="ativo">
                <option value="1" <?= (int)$usuarioEdicao['ativo'] === 1 ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= (int)$usuarioEdicao['ativo'] === 0 ? 'selected' : '' ?>>Inativo</option>
              </select>
            </div>
          </div>
          <div style="margin-top:10px;"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
        </form>

        <form method="post" style="margin-top:10px;">
          <?= csrf_input() ?>
          <input type="hidden" name="acao" value="trocar_senha_usuario">
          <input type="hidden" name="usuario_id" value="<?= e((string)$usuarioEdicao['id']) ?>">
          <div class="grid-3">
            <div><label>Nova senha</label><input name="nova_senha" type="password" minlength="8" required></div>
          </div>
          <div style="margin-top:10px;"><button class="btn btn-warning" type="submit">Atualizar senha</button></div>
        </form>

        <?php if ((int)($usuario['id'] ?? 0) !== (int)$usuarioEdicao['id']): ?>
          <form method="post" style="margin-top:10px;">
            <?= csrf_input() ?>
            <input type="hidden" name="acao" value="excluir_usuario">
            <input type="hidden" name="usuario_id" value="<?= e((string)$usuarioEdicao['id']) ?>">
            <button class="btn btn-danger" type="submit" data-confirm="Confirma excluir este usuário? Esta ação é definitiva.">
              Excluir usuário
            </button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="card">
      <h2>Usuários cadastrados</h2>
      <form method="get" class="grid-3">
        <div><label>Buscar por nome, email ou usuário</label><input name="q" value="<?= e($busca) ?>"></div>
        <div style="align-self:end;"><button class="btn btn-primary" type="submit">Pesquisar</button></div>
      </form>

      <table class="tabela-testes" style="margin-top:10px;">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Usuário</th>
            <th>Cargo</th>
            <th>Status</th>
            <th>Criado em</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$usuarios): ?>
            <tr><td colspan="8">Nenhum usuário encontrado.</td></tr>
          <?php endif; ?>
          <?php foreach ($usuarios as $item): ?>
            <tr>
              <td><?= e((string)$item['id']) ?></td>
              <td><?= e($item['nome']) ?></td>
              <td><?= e($item['email']) ?></td>
              <td><?= e($item['usuario'] ?: '-') ?></td>
              <td><?= e(strtoupper($item['nivel'])) ?></td>
              <td><?= (int)$item['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td>
              <td><?= e(formatar_data($item['criado_em'])) ?></td>
              <td>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <a class="btn btn-neutral" href="/admin/usuarios.php?editar=<?= e((string)$item['id']) ?>">Editar</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
  <script src="/admin/assets/admin.js"></script>
</body>
</html>
