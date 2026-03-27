<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

exigir_login();
$pdo = obter_conexao();
$usuario = usuario_atual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_pessoa') {
            $id = criar_pessoa(
                $pdo,
                (string)($_POST['nome'] ?? ''),
                (string)($_POST['documento'] ?? ''),
                (string)($_POST['telefone'] ?? ''),
                (string)($_POST['email'] ?? '')
            );
            registrar_log($pdo, 'criar_pessoa', "Pessoa {$id} criada");
            definir_flash('sucesso', 'Pessoa cadastrada com sucesso.');
            redirecionar('/portal/pessoas.php?editar=' . $id);
        }

        if ($acao === 'editar_pessoa') {
            $pessoaId = (int)($_POST['pessoa_id'] ?? 0);
            if ($pessoaId <= 0) {
                throw new RuntimeException('Pessoa inválida para edição.');
            }
            atualizar_pessoa(
                $pdo,
                $pessoaId,
                (string)($_POST['nome'] ?? ''),
                (string)($_POST['documento'] ?? ''),
                (string)($_POST['telefone'] ?? ''),
                (string)($_POST['email'] ?? '')
            );
            registrar_log($pdo, 'editar_pessoa', "Pessoa {$pessoaId} editada");
            definir_flash('sucesso', 'Dados da pessoa atualizados.');
            redirecionar('/portal/pessoas.php?editar=' . $pessoaId);
        }

        if ($acao === 'excluir_pessoa') {
            $pessoaId = (int)($_POST['pessoa_id'] ?? 0);
            if ($pessoaId <= 0) {
                throw new RuntimeException('Pessoa inválida para exclusão.');
            }
            excluir_pessoa($pdo, $pessoaId);
            registrar_log($pdo, 'excluir_pessoa', "Pessoa {$pessoaId} excluída");
            definir_flash('sucesso', 'Pessoa excluída com sucesso.');
            redirecionar('/portal/pessoas.php');
        }

        throw new RuntimeException('Ação inválida.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            definir_flash('erro', 'Documento duplicado. Verifique o cadastro existente.');
            redirecionar('/portal/pessoas.php');
        }
        definir_flash('erro', 'Erro ao salvar pessoa. Verifique os dados e tente novamente.');
        redirecionar('/portal/pessoas.php');
    } catch (Throwable $e) {
        definir_flash('erro', $e->getMessage());
        redirecionar('/portal/pessoas.php');
    }
}

$flash = consumir_flash();
$busca = trim((string)($_GET['q'] ?? ''));
$pessoas = listar_pessoas($pdo, $busca, 250);
$pessoaEdicao = null;
$historicoPessoa = [];
$editarId = (int)($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $pessoaEdicao = buscar_pessoa_por_id($pdo, $editarId);
    if ($pessoaEdicao) {
        $historicoPessoa = listar_historico_pessoa($pdo, (int)$pessoaEdicao['id']);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cadastro de Hóspedes - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/portal/assets/admin.css">
</head>
<body>
  <main class="admin-page">
    <header class="topbar">
      <div>
        <h1>Cadastro de Hóspedes</h1>
        <small>Pesquisar, editar e consultar histórico de estadias</small>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-neutral" href="/portal/index.php">Dashboard</a>
        <a class="btn btn-neutral" href="/portal/historico.php">Histórico</a>
        <?php if (usuario_eh_master()): ?>
          <a class="btn btn-neutral" href="/portal/usuarios.php">Usuários</a>
        <?php endif; ?>
        <form method="post" action="/portal/logout.php" class="inline-form"><?= csrf_input() ?><button class="btn btn-primary" type="submit">Sair</button></form>
      </div>
    </header>

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['tipo']) ?>" data-flash><?= e($flash['mensagem']) ?></div><?php endif; ?>

    <section class="card">
      <h2>Pesquisar pessoas</h2>
      <form method="get" class="grid-3">
        <div>
          <label>Nome ou telefone</label>
          <input name="q" value="<?= e($busca) ?>" placeholder="Ex.: Maria ou 3399">
        </div>
        <div style="align-self:end;">
          <button class="btn btn-primary" type="submit">Pesquisar</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Cadastrar nova pessoa</h2>
      <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="acao" value="criar_pessoa">
        <div class="grid-3">
          <div><label>Nome</label><input name="nome" required></div>
          <div><label>Documento</label><input name="documento"></div>
          <div><label>Telefone</label><input name="telefone"></div>
          <div><label>Email</label><input name="email" type="email"></div>
        </div>
        <div style="margin-top:10px;"><button class="btn btn-success" type="submit">Cadastrar pessoa</button></div>
      </form>
    </section>

    <?php if ($pessoaEdicao): ?>
      <section class="card">
        <h2>Editar pessoa #<?= e((string)$pessoaEdicao['id']) ?></h2>
        <form method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="acao" value="editar_pessoa">
          <input type="hidden" name="pessoa_id" value="<?= e((string)$pessoaEdicao['id']) ?>">
          <div class="grid-3">
            <div><label>Nome</label><input name="nome" value="<?= e($pessoaEdicao['nome']) ?>" required></div>
            <div><label>Documento</label><input name="documento" value="<?= e($pessoaEdicao['documento']) ?>"></div>
            <div><label>Telefone</label><input name="telefone" value="<?= e($pessoaEdicao['telefone']) ?>"></div>
            <div><label>Email</label><input name="email" type="email" value="<?= e($pessoaEdicao['email']) ?>"></div>
          </div>
          <div style="margin-top:10px;"><button class="btn btn-primary" type="submit">Salvar alterações</button></div>
        </form>
        <div style="margin-top:10px;">
          <a class="btn btn-success" href="/portal/pessoa_imprimir.php?id=<?= e((string)$pessoaEdicao['id']) ?>" target="_blank" rel="noopener noreferrer">
            Imprimir ficha
          </a>
        </div>
        <form method="post" style="margin-top:10px;">
          <?= csrf_input() ?>
          <input type="hidden" name="acao" value="excluir_pessoa">
          <input type="hidden" name="pessoa_id" value="<?= e((string)$pessoaEdicao['id']) ?>">
          <button class="btn btn-danger" type="submit" data-confirm="Confirma excluir este hóspede? O sistema também apagará todo o histórico de hospedagem e vínculos de acompanhantes desta pessoa.">
            Excluir hóspede
          </button>
        </form>
      </section>

      <section class="card">
        <h2>Histórico da pessoa</h2>
        <table class="tabela-testes">
          <thead>
            <tr>
              <th>Estadia</th>
              <th>Quarto</th>
              <th>Status</th>
              <th>Check-in previsto</th>
              <th>Check-out previsto</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$historicoPessoa): ?>
              <tr><td colspan="5">Sem histórico encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($historicoPessoa as $item): ?>
              <tr>
                <td>#<?= e((string)$item['id']) ?></td>
                <td><?= e($item['quarto_numero']) ?> (<?= e($item['quarto_tipo']) ?>)</td>
                <td><?= e($item['status']) ?></td>
                <td><?= e(formatar_data($item['data_checkin_previsto'])) ?></td>
                <td><?= e(formatar_data($item['data_checkout_previsto'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <section class="card">
      <h2>Resultados</h2>
      <table class="tabela-testes">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Telefone</th>
            <th>Documento</th>
            <th>Estadias</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pessoas): ?>
            <tr><td colspan="6">Nenhuma pessoa encontrada.</td></tr>
          <?php endif; ?>
          <?php foreach ($pessoas as $pessoa): ?>
            <tr>
              <td><?= e((string)$pessoa['id']) ?></td>
              <td><?= e($pessoa['nome']) ?></td>
              <td><?= e($pessoa['telefone'] ?: '-') ?></td>
              <td><?= e($pessoa['documento'] ?: '-') ?></td>
              <td><?= e((string)$pessoa['total_estadias']) ?></td>
              <td>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <a class="btn btn-neutral" href="/portal/pessoas.php?editar=<?= e((string)$pessoa['id']) ?>">Editar / Histórico</a>
                  <a class="btn btn-success" href="/portal/pessoa_imprimir.php?id=<?= e((string)$pessoa['id']) ?>" target="_blank" rel="noopener noreferrer">Imprimir ficha</a>
                  <form method="post" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="acao" value="excluir_pessoa">
                    <input type="hidden" name="pessoa_id" value="<?= e((string)$pessoa['id']) ?>">
                    <button class="btn btn-danger" type="submit" data-confirm="Confirma excluir este hóspede? O sistema também apagará todo o histórico de hospedagem e vínculos de acompanhantes desta pessoa.">
                      Excluir
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
  <script src="/portal/assets/admin.js"></script>
</body>
</html>
