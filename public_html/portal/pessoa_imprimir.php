<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/csrf.php';

exigir_login();
$pdo = obter_conexao();

$pessoaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pessoaId || $pessoaId <= 0) {
    definir_flash('erro', 'Pessoa inválida para impressão.');
    redirecionar('/portal/pessoas.php');
}

$pessoa = buscar_pessoa_por_id($pdo, (int)$pessoaId);
if (!$pessoa) {
    definir_flash('erro', 'Pessoa não encontrada para impressão.');
    redirecionar('/portal/pessoas.php');
}

$historico = listar_historico_pessoa($pdo, (int)$pessoaId, 300);
$geradoEm = (new DateTimeImmutable('now'))->format('d/m/Y H:i');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ficha do Cliente #<?= e((string)$pessoa['id']) ?> - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/portal/assets/admin.css">
  <style>
    .ficha-wrap {
      max-width: 980px;
      margin: 0 auto;
      padding: 18px;
    }

    .ficha-empresa {
      border: 1px solid #ced8ee;
      border-radius: 14px;
      padding: 12px;
      background: linear-gradient(145deg, #f7f9ff 0%, #eef3ff 100%);
      margin-bottom: 12px;
    }

    .ficha-empresa h1 {
      margin: 0 0 4px;
      font-size: 1.1rem;
    }

    .ficha-empresa p {
      margin: 3px 0;
      font-size: 0.86rem;
      color: #2a4169;
    }

    .ficha-card {
      border: 1px solid #d6e0f3;
      border-radius: 14px;
      background: #fff;
      padding: 12px;
      margin-bottom: 12px;
    }

    .ficha-card h2 {
      margin: 0 0 10px;
      font-size: 0.96rem;
      color: #203a64;
    }

    .ficha-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      font-size: 0.88rem;
    }

    .ficha-linha strong {
      color: #1f3b67;
    }

    @media (min-width: 760px) {
      .ficha-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
  </style>
</head>
<body>
  <main class="ficha-wrap">
    <div class="no-print" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
      <a class="btn btn-neutral" href="/portal/pessoas.php?editar=<?= e((string)$pessoa['id']) ?>">Voltar ao cadastro</a>
      <button class="btn btn-success" type="button" onclick="window.print()">Imprimir ficha</button>
    </div>

    <section class="ficha-empresa">
      <h1>Hotel Bela Vista - Guanhães/MG</h1>
      <p><strong>Documento interno:</strong> Ficha de Cliente Cadastrado</p>
      <p><strong>WhatsApp reservas:</strong> (33) 99812-2068</p>
      <p><strong>Gerado em:</strong> <?= e($geradoEm) ?></p>
    </section>

    <section class="ficha-card">
      <h2>Dados do Cliente</h2>
      <div class="ficha-grid">
        <div class="ficha-linha"><strong>ID:</strong> #<?= e((string)$pessoa['id']) ?></div>
        <div class="ficha-linha"><strong>Nome:</strong> <?= e($pessoa['nome']) ?></div>
        <div class="ficha-linha"><strong>Documento:</strong> <?= e($pessoa['documento'] ?: '-') ?></div>
        <div class="ficha-linha"><strong>Telefone:</strong> <?= e($pessoa['telefone'] ?: '-') ?></div>
        <div class="ficha-linha"><strong>Email:</strong> <?= e($pessoa['email'] ?: '-') ?></div>
        <div class="ficha-linha"><strong>Cadastrado em:</strong> <?= e(formatar_data($pessoa['criado_em'] ?? null)) ?></div>
      </div>
    </section>

    <section class="ficha-card">
      <h2>Histórico de Estadias (<?= e((string)count($historico)) ?>)</h2>
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
          <?php if (!$historico): ?>
            <tr><td colspan="5">Sem histórico de estadias para este cliente.</td></tr>
          <?php endif; ?>
          <?php foreach ($historico as $item): ?>
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
  </main>
</body>
</html>
