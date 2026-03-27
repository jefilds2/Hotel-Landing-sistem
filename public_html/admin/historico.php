<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

exigir_login();
$pdo = obter_conexao();
$usuario = usuario_atual();

$inicioData = trim((string)($_GET['inicio'] ?? ''));
$fimData = trim((string)($_GET['fim'] ?? ''));
$quartoId = (int)($_GET['quarto_id'] ?? 0);
$pessoaId = (int)($_GET['pessoa_id'] ?? 0);
$pessoaBusca = trim((string)($_GET['pessoa_busca'] ?? ''));

$inicioFiltro = $inicioData !== '' ? $inicioData . ' 00:00:00' : null;
$fimFiltro = $fimData !== '' ? $fimData . ' 23:59:59' : null;
$historico = listar_historico_estadias(
    $pdo,
    $inicioFiltro,
    $fimFiltro,
    $quartoId > 0 ? $quartoId : null,
    $pessoaId > 0 ? $pessoaId : null,
    $pessoaBusca !== '' ? $pessoaBusca : null
);

if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historico-estadias.csv');
    $saida = fopen('php://output', 'w');
    fputcsv($saida, ['Estadia', 'Quarto', 'Hospede principal', 'Status', 'Check-in previsto', 'Check-out previsto', 'Diarias', 'Valor diaria', 'Forma de pagamento']);
    foreach ($historico as $item) {
        $valorDiaria = $item['valor_diaria'] !== null ? number_format((float)$item['valor_diaria'], 2, ',', '.') : '';
        fputcsv($saida, [
            '#' . $item['id'],
            $item['quarto_numero'],
            $item['hospede_nome'],
            $item['status'],
            $item['data_checkin_previsto'],
            $item['data_checkout_previsto'],
            $item['quantidade_diarias'] ?? '',
            $valorDiaria,
            $item['forma_pagamento'] ?? '',
        ]);
    }
    fclose($saida);
    exit;
}

$quartos = $pdo->query('SELECT id, numero, tipo FROM quartos ORDER BY LENGTH(numero), numero')->fetchAll();
$pessoas = listar_pessoas_para_select($pdo, '', 200);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Histórico de Estadias - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
  <main class="admin-page">
    <header class="topbar">
      <div>
        <h1>Histórico de Estadias</h1>
        <small>Filtro por período, quarto e pessoa</small>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-neutral" href="/admin/index.php">Dashboard</a>
        <a class="btn btn-neutral" href="/admin/pessoas.php">Cadastro de hóspedes</a>
        <?php if (usuario_eh_master()): ?>
          <a class="btn btn-neutral" href="/admin/usuarios.php">Usuários</a>
        <?php endif; ?>
        <form method="post" action="/admin/logout.php" class="inline-form"><?= csrf_input() ?><button class="btn btn-primary" type="submit">Sair</button></form>
      </div>
    </header>

    <section class="card no-print">
      <h2>Filtros</h2>
      <form method="get" class="grid-3">
        <div><label>Data inicial</label><input type="date" name="inicio" value="<?= e($inicioData) ?>"></div>
        <div><label>Data final</label><input type="date" name="fim" value="<?= e($fimData) ?>"></div>
        <div>
          <label>Quarto</label>
          <select name="quarto_id">
            <option value="0">Todos</option>
            <?php foreach ($quartos as $quarto): ?>
              <option value="<?= e((string)$quarto['id']) ?>" <?= (int)$quarto['id'] === $quartoId ? 'selected' : '' ?>>
                <?= e($quarto['numero']) ?> (<?= e($quarto['tipo']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="filtro-pessoa-historico">Buscar pessoa</label>
          <input id="filtro-pessoa-historico" type="text" name="pessoa_busca" value="<?= e($pessoaBusca) ?>" placeholder="Digite parte do nome..." data-select-filter="select-pessoa-historico" list="lista-pessoa-historico" autocomplete="off">
          <datalist id="lista-pessoa-historico">
            <?php foreach ($pessoas as $pessoa): ?>
              <option value="<?= e($pessoa['nome']) ?>"><?= e($pessoa['nome']) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label for="select-pessoa-historico">Pessoa</label>
          <select id="select-pessoa-historico" name="pessoa_id">
            <option value="0">Todas</option>
            <?php foreach ($pessoas as $pessoa): ?>
              <option value="<?= e((string)$pessoa['id']) ?>" <?= (int)$pessoa['id'] === $pessoaId ? 'selected' : '' ?>>
                <?= e($pessoa['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="align-self:end;">
          <button class="btn btn-primary" type="submit">Aplicar filtros</button>
        </div>
        <div style="align-self:end;">
          <button class="btn btn-success" type="button" onclick="window.print()">Imprimir</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>Resultados (<?= e((string)count($historico)) ?>)</h2>
      <table class="tabela-testes">
        <thead>
          <tr>
            <th>Estadia</th>
            <th>Quarto</th>
            <th>Hóspede principal</th>
            <th>Status</th>
            <th>Check-in previsto</th>
            <th>Check-out previsto</th>
            <th>Diárias</th>
            <th>Valor diária</th>
            <th>Pagamento</th>
            <th class="no-print">Abrir</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$historico): ?>
            <tr><td colspan="10">Nenhum registro encontrado com os filtros informados.</td></tr>
          <?php endif; ?>
          <?php foreach ($historico as $item): ?>
            <tr>
              <td>#<?= e((string)$item['id']) ?></td>
              <td><?= e($item['quarto_numero']) ?></td>
              <td><?= e($item['hospede_nome']) ?></td>
              <td><?= e($item['status']) ?></td>
              <td><?= e(formatar_data($item['data_checkin_previsto'])) ?></td>
              <td><?= e(formatar_data($item['data_checkout_previsto'])) ?></td>
              <td><?= e((string)($item['quantidade_diarias'] ?? '-')) ?></td>
              <td>
                <?php if ($item['valor_diaria'] !== null): ?>
                  R$ <?= e(number_format((float)$item['valor_diaria'], 2, ',', '.')) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td><?= e($item['forma_pagamento'] ?: '-') ?></td>
              <td class="no-print"><a class="btn btn-neutral" href="/admin/quarto.php?id=<?= e((string)$item['quarto_id']) ?>&estadia_id=<?= e((string)$item['id']) ?>">Abrir quarto</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
  <script src="/admin/assets/admin.js"></script>
</body>
</html>
