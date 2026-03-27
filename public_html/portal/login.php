<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (usuario_logado()) {
  redirecionar('/portal/index.php');
}

$flash = consumir_flash();
$erroLogin = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  exigir_csrf();

  $email = (string) ($_POST['email'] ?? '');
  $senha = (string) ($_POST['senha'] ?? '');

  $resultado = autenticar_usuario($email, $senha);
  if ($resultado['ok']) {
    definir_flash('sucesso', 'Bem-vindo ao painel do Hotel Bela Vista.');
    redirecionar('/portal/index.php');
  }

  $erroLogin = $resultado['mensagem'];
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/portal/assets/admin.css">
</head>

<body>
  <main class="login-wrap">
    <section class="login-card">
      <h1>Painel Administrativo</h1>
      <p>Controle de quartos, estadias e operação diária.</p>

      <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['tipo']) ?>" data-flash><?= e($flash['mensagem']) ?></div>
      <?php endif; ?>

      <?php if ($erroLogin): ?>
        <div class="flash flash-erro" data-flash><?= e($erroLogin) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_input() ?>
        <div style="margin-bottom:10px;">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required>
        </div>
        <div style="margin-bottom:14px;">
          <label for="senha">Senha</label>
          <input id="senha" name="senha" type="password" required>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Entrar no painel</button>
      </form>
    </section>
  </main>
  <script src="/portal/assets/admin.js"></script>
</body>

</html>