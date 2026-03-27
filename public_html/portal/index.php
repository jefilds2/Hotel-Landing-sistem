<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/csrf.php';

exigir_login();

$flash = consumir_flash();
$pdo = obter_conexao();
$usuario = usuario_atual();

$statusFiltro = (string)($_GET['status'] ?? 'todos');
$statusValidos = ['todos', 'livre', 'reservado', 'ocupado', 'limpeza', 'manutencao'];
if (!in_array($statusFiltro, $statusValidos, true)) {
    $statusFiltro = 'todos';
}

$sqlQuartos = '
    SELECT
      q.*,
      CASE
        WHEN EXISTS (
          SELECT 1
          FROM estadias ex
          WHERE ex.quarto_id = q.id
            AND ex.status = \'hospedado\'
          LIMIT 1
        ) THEN \'ocupado\'
        ELSE q.status
      END AS status_exibicao,
      eh.id AS estadia_hospedado_id,
      eh.data_checkin_previsto AS hospedado_checkin,
      eh.data_checkout_previsto AS hospedado_checkout,
      ph.nome AS hospedado_nome,
      ea.id AS proxima_estadia_id,
      ea.data_checkin_previsto AS proxima_checkin,
      ea.data_checkout_previsto AS proxima_checkout,
      pa.nome AS proxima_nome
    FROM quartos q
    LEFT JOIN estadias eh ON eh.id = (
      SELECT e2.id
      FROM estadias e2
      WHERE e2.quarto_id = q.id
        AND e2.status = \'hospedado\'
      ORDER BY e2.data_checkin_previsto ASC, e2.id DESC
      LIMIT 1
    )
    LEFT JOIN pessoas ph ON ph.id = eh.hospede_principal_id
    LEFT JOIN estadias ea ON ea.id = (
      SELECT e3.id
      FROM estadias e3
      WHERE e3.quarto_id = q.id
        AND e3.status = \'reservada\'
      ORDER BY e3.data_checkin_previsto ASC, e3.id DESC
      LIMIT 1
    )
    LEFT JOIN pessoas pa ON pa.id = ea.hospede_principal_id
';

$sqlQuartos .= ' ORDER BY LENGTH(q.numero), q.numero';

$stmtQuartos = $pdo->prepare($sqlQuartos);
$stmtQuartos->execute();
$quartos = $stmtQuartos->fetchAll();

$totais = [
    'livre' => 0,
    'reservado' => 0,
    'ocupado' => 0,
    'limpeza' => 0,
    'manutencao' => 0,
];

foreach ($quartos as $linha) {
    $statusReal = (string)($linha['status_exibicao'] ?? 'livre');
    if (isset($totais[$statusReal])) {
        $totais[$statusReal]++;
    }
}

if ($statusFiltro !== 'todos') {
    $quartos = array_values(array_filter(
        $quartos,
        static fn(array $item): bool => (string)($item['status_exibicao'] ?? '') === $statusFiltro
    ));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/portal/assets/admin.css">
</head>
<body>
  <main class="admin-page">
    <header class="topbar">
      <div>
        <h1>Dashboard de Quartos - <?= e(APP_NOME) ?></h1>
        <small>Usuário: <?= e($usuario['nome'] ?? '-') ?> (<?= e($usuario['nivel'] ?? '-') ?>)</small>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-neutral" href="/portal/pessoas.php">Cadastro de hóspedes</a>
        <a class="btn btn-neutral" href="/portal/historico.php">Histórico</a>
        <?php if (usuario_eh_master()): ?>
          <a class="btn btn-neutral" href="/portal/usuarios.php">Usuários</a>
        <?php endif; ?>
        <form method="post" action="/portal/logout.php" class="inline-form">
          <?= csrf_input() ?>
          <button class="btn btn-primary" type="submit">Sair</button>
        </form>
      </div>
    </header>

    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['tipo']) ?>" data-flash><?= e($flash['mensagem']) ?></div>
    <?php endif; ?>

    <section class="stats-grid">
      <article class="stat-card">
        <h3>Livres</h3>
        <p><?= e((string)$totais['livre']) ?></p>
      </article>
      <article class="stat-card">
        <h3>Reservados</h3>
        <p><?= e((string)$totais['reservado']) ?></p>
      </article>
      <article class="stat-card">
        <h3>Ocupados</h3>
        <p><?= e((string)$totais['ocupado']) ?></p>
      </article>
      <article class="stat-card">
        <h3>Limpeza / Manutenção</h3>
        <p><?= e((string)($totais['limpeza'] + $totais['manutencao'])) ?></p>
      </article>
    </section>

    <nav class="filtros">
      <?php foreach ($statusValidos as $status): ?>
        <?php
          $rotulo = $status === 'todos' ? 'Todos' : ucfirst($status);
          $classeAtivo = $statusFiltro === $status ? 'ativo' : '';
        ?>
        <a class="filtro-link <?= e($classeAtivo) ?>" href="/portal/index.php?status=<?= e($status) ?>">
          <?= e($rotulo) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <section class="quartos-grid">
      <?php foreach ($quartos as $quarto): ?>
        <a class="quarto-card quarto-status-<?= e($quarto['status_exibicao']) ?>" href="/portal/quarto.php?id=<?= e((string)$quarto['id']) ?>">
          <div class="quarto-topo">
            <div>
              <div class="quarto-numero"><?= e($quarto['numero']) ?></div>
              <div class="texto-mudo"><?= e($quarto['tipo']) ?></div>
            </div>
            <span class="badge <?= e(classe_status_quarto($quarto['status_exibicao'])) ?>">
              <?= e($quarto['status_exibicao']) ?>
            </span>
          </div>
          <div class="quarto-meta">
            <span><strong>Banheiro:</strong> <?= e($quarto['banheiro']) ?></span>
            <span><strong>Camas:</strong> <?= e($quarto['camas']) ?></span>
          </div>

          <?php if (!empty($quarto['estadia_hospedado_id'])): ?>
            <div class="quarto-ocupacao">
              <strong>Hóspede:</strong> <?= e($quarto['hospedado_nome'] ?? '-') ?><br>
              <strong>Check-in:</strong> <?= e(formatar_data($quarto['hospedado_checkin'])) ?><br>
              <strong>Check-out:</strong> <?= e(formatar_data($quarto['hospedado_checkout'])) ?><br>
              <strong>Estadia:</strong> hospedado
            </div>
          <?php elseif (!empty($quarto['proxima_estadia_id'])): ?>
            <div class="quarto-ocupacao">
              <strong>Próxima reserva:</strong> <?= e($quarto['proxima_nome'] ?? '-') ?><br>
              <strong>Entrada:</strong> <?= e(formatar_data($quarto['proxima_checkin'])) ?><br>
              <strong>Saída:</strong> <?= e(formatar_data($quarto['proxima_checkout'])) ?>
            </div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </section>
  </main>
  <script src="/portal/assets/admin.js"></script>
</body>
</html>
