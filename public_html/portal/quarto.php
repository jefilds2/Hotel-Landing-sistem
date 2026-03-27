<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

exigir_login();
$pdo = obter_conexao();
$usuario = usuario_atual();

$quartoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$quartoId) {
    definir_flash('erro', 'Quarto inválido.');
    redirecionar('/portal/index.php');
}
$quarto = buscar_quarto_por_id($pdo, (int)$quartoId);
if (!$quarto) {
    definir_flash('erro', 'Quarto não encontrado.');
    redirecionar('/portal/index.php');
}

function resolver_principal(PDO $pdo, array $dados): int
{
    $pessoaId = (int)($dados['hospede_principal_id'] ?? 0);
    if ($pessoaId > 0) {
        $pessoa = buscar_pessoa_por_id($pdo, $pessoaId);
        if (!$pessoa) {
            throw new RuntimeException('Pessoa principal inválida.');
        }
        return $pessoaId;
    }

    $nome = trim((string)($dados['hospede_nome'] ?? ''));
    if ($nome === '') {
        throw new RuntimeException('Selecione uma pessoa ou informe nome para cadastro rápido.');
    }

    return obter_ou_criar_pessoa(
        $pdo,
        $nome,
        (string)($dados['hospede_documento'] ?? ''),
        (string)($dados['hospede_telefone'] ?? ''),
        (string)($dados['hospede_email'] ?? '')
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = (string)($_POST['acao'] ?? '');
    $estadiaIdPost = (int)($_POST['estadia_id'] ?? 0);

    try {
        if ($acao === 'criar_estadia' || $acao === 'editar_estadia') {
            [$okPeriodo, $msgPeriodo, $checkinNorm, $checkoutNorm] = normalizar_periodo_estadia(
                (string)($_POST['data_checkin_previsto'] ?? ''),
                (string)($_POST['data_checkout_previsto'] ?? '')
            );
            if (!$okPeriodo || $checkinNorm === null || $checkoutNorm === null) {
                throw new RuntimeException($msgPeriodo);
            }

            $principalId = resolver_principal($pdo, $_POST);
            $observacoes = trim((string)($_POST['observacoes'] ?? ''));
            $quantidadeDiariasCalculada = calcular_quantidade_diarias($checkinNorm, $checkoutNorm);
            $quantidadeDiarias = (int)($_POST['quantidade_diarias'] ?? 0);
            if ($quantidadeDiarias <= 0) {
                $quantidadeDiarias = $quantidadeDiariasCalculada;
            }
            $valorDiaria = normalizar_valor_monetario((string)($_POST['valor_diaria'] ?? ''));
            $formaPagamento = trim((string)($_POST['forma_pagamento'] ?? ''));
            if ($formaPagamento === '') {
                $formaPagamento = null;
            } elseif (strlen($formaPagamento) > 50) {
                throw new RuntimeException('Forma de pagamento muito longa.');
            }

            if ($acao === 'criar_estadia') {
                $statusInicial = (string)($_POST['status_inicial'] ?? 'reservada');
                if (!in_array($statusInicial, ['reservada', 'hospedado'], true)) {
                    throw new RuntimeException('Status inicial inválido.');
                }

                if (existe_conflito_reserva($pdo, (int)$quartoId, $checkinNorm, $checkoutNorm)) {
                    throw new RuntimeException('Conflito de reserva para este quarto/período.');
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('
                    INSERT INTO estadias (
                        quarto_id,
                        hospede_principal_id,
                        data_checkin_previsto,
                        data_checkout_previsto,
                        quantidade_diarias,
                        valor_diaria,
                        forma_pagamento,
                        data_checkin_real,
                        status,
                        observacoes
                    ) VALUES (
                        :quarto_id,
                        :principal_id,
                        :checkin,
                        :checkout,
                        :quantidade_diarias,
                        :valor_diaria,
                        :forma_pagamento,
                        :checkin_real,
                        :status,
                        :observacoes
                    )
                ');
                $stmt->execute([
                    'quarto_id' => $quartoId,
                    'principal_id' => $principalId,
                    'checkin' => $checkinNorm,
                    'checkout' => $checkoutNorm,
                    'quantidade_diarias' => $quantidadeDiarias,
                    'valor_diaria' => $valorDiaria,
                    'forma_pagamento' => $formaPagamento,
                    'checkin_real' => $statusInicial === 'hospedado' ? date('Y-m-d H:i:s') : null,
                    'status' => $statusInicial,
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                ]);
                $novaEstadiaId = (int)$pdo->lastInsertId();
                atualizar_status_quarto($pdo, (int)$quartoId, $statusInicial === 'hospedado' ? 'ocupado' : 'reservado');
                $pdo->commit();
                registrar_log($pdo, 'criar_estadia', "Estadia {$novaEstadiaId} no quarto {$quarto['numero']}");
                definir_flash('sucesso', 'Estadia criada.');
                redirecionar('/portal/quarto.php?id=' . $quartoId . '&estadia_id=' . $novaEstadiaId);
            }

            if ($estadiaIdPost <= 0) {
                throw new RuntimeException('Estadia inválida para edição.');
            }
            $estadia = buscar_estadia_por_id($pdo, $estadiaIdPost);
            if (!$estadia || (int)$estadia['quarto_id'] !== (int)$quartoId || !in_array($estadia['status'], ['reservada', 'hospedado'], true)) {
                throw new RuntimeException('Estadia não pode ser editada.');
            }
            if (existe_conflito_reserva($pdo, (int)$quartoId, $checkinNorm, $checkoutNorm, $estadiaIdPost)) {
                throw new RuntimeException('Conflito de reserva: ajuste as datas.');
            }

            $stmt = $pdo->prepare('
                UPDATE estadias
                SET hospede_principal_id = :principal_id,
                    data_checkin_previsto = :checkin,
                    data_checkout_previsto = :checkout,
                    quantidade_diarias = :quantidade_diarias,
                    valor_diaria = :valor_diaria,
                    forma_pagamento = :forma_pagamento,
                    observacoes = :observacoes
                WHERE id = :id
            ');
            $stmt->execute([
                'principal_id' => $principalId,
                'checkin' => $checkinNorm,
                'checkout' => $checkoutNorm,
                'quantidade_diarias' => $quantidadeDiarias,
                'valor_diaria' => $valorDiaria,
                'forma_pagamento' => $formaPagamento,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'id' => $estadiaIdPost,
            ]);
            registrar_log($pdo, 'editar_estadia', "Estadia {$estadiaIdPost} no quarto {$quarto['numero']}");
            definir_flash('sucesso', 'Estadia atualizada.');
            redirecionar('/portal/quarto.php?id=' . $quartoId . '&estadia_id=' . $estadiaIdPost);
        }

        if ($acao === 'confirmar_checkin') {
            $estadia = buscar_estadia_por_id($pdo, $estadiaIdPost);
            if (!$estadia || (int)$estadia['quarto_id'] !== (int)$quartoId || $estadia['status'] !== 'reservada') {
                throw new RuntimeException('Somente reserva ativa pode virar check-in.');
            }
            $stmt = $pdo->prepare('UPDATE estadias SET status = :status, data_checkin_real = NOW() WHERE id = :id');
            $stmt->execute(['status' => 'hospedado', 'id' => $estadiaIdPost]);
            atualizar_status_quarto($pdo, (int)$quartoId, 'ocupado');
            registrar_log($pdo, 'confirmar_checkin', "Estadia {$estadiaIdPost}");
            definir_flash('sucesso', 'Check-in confirmado.');
            redirecionar('/portal/quarto.php?id=' . $quartoId . '&estadia_id=' . $estadiaIdPost);
        }

        if ($acao === 'check_out') {
            $estadia = buscar_estadia_por_id($pdo, $estadiaIdPost);
            if (!$estadia || (int)$estadia['quarto_id'] !== (int)$quartoId || $estadia['status'] !== 'hospedado') {
                throw new RuntimeException('Somente hospedagem ativa permite check-out.');
            }
            $stmt = $pdo->prepare('UPDATE estadias SET status = :status, data_checkout_real = NOW() WHERE id = :id');
            $stmt->execute(['status' => 'finalizada', 'id' => $estadiaIdPost]);
            atualizar_status_quarto($pdo, (int)$quartoId, 'limpeza');
            registrar_log($pdo, 'check_out', "Estadia {$estadiaIdPost}");
            definir_flash('sucesso', 'Check-out finalizado e quarto em limpeza.');
            redirecionar('/portal/quarto.php?id=' . $quartoId);
        }

        if ($acao === 'cancelar_reserva') {
            $estadia = buscar_estadia_por_id($pdo, $estadiaIdPost);
            if (!$estadia || (int)$estadia['quarto_id'] !== (int)$quartoId || !in_array($estadia['status'], ['reservada', 'hospedado'], true)) {
                throw new RuntimeException('Somente estadia ativa pode ser cancelada.');
            }
            $stmt = $pdo->prepare('UPDATE estadias SET status = :status WHERE id = :id');
            $stmt->execute(['status' => 'cancelada', 'id' => $estadiaIdPost]);
            atualizar_status_quarto($pdo, (int)$quartoId, 'livre');
            registrar_log($pdo, 'cancelar_reserva', "Estadia {$estadiaIdPost}");
            definir_flash('sucesso', 'Reserva cancelada. Quarto livre.');
            redirecionar('/portal/quarto.php?id=' . $quartoId);
        }

        if ($acao === 'marcar_hospedado' || $acao === 'marcar_reservado' || $acao === 'marcar_limpeza' || $acao === 'marcar_manutencao' || $acao === 'marcar_livre') {
            $novoStatus = match ($acao) {
                'marcar_hospedado' => 'ocupado',
                'marcar_reservado' => 'reservado',
                'marcar_limpeza' => 'limpeza',
                'marcar_manutencao' => 'manutencao',
                default => 'livre',
            };
            atualizar_status_quarto($pdo, (int)$quartoId, $novoStatus);
            registrar_log($pdo, 'alterar_status_quarto', "Quarto {$quarto['numero']} => {$novoStatus}");
            definir_flash('sucesso', 'Status do quarto atualizado.');
            redirecionar('/portal/quarto.php?id=' . $quartoId);
        }

        if ($acao === 'adicionar_acompanhantes') {
            $estadia = buscar_estadia_por_id($pdo, $estadiaIdPost);
            if (!$estadia || (int)$estadia['quarto_id'] !== (int)$quartoId || !in_array($estadia['status'], ['reservada', 'hospedado'], true)) {
                throw new RuntimeException('Acompanhantes só podem ser alterados em estadia ativa.');
            }

            $vinculados = [];
            $normalizarChave = static function (string $nome, string $documento, string $telefone): string {
                $nomeN = strtolower(trim($nome));
                $docN = preg_replace('/\W+/', '', strtolower(trim($documento))) ?? '';
                $telN = preg_replace('/\D+/', '', trim($telefone)) ?? '';
                return $nomeN . '|' . $docN . '|' . $telN;
            };
            $chavesUsadas = [];
            $chavesUsadas[] = $normalizarChave(
                (string)$estadia['hospede_nome'],
                (string)($estadia['hospede_documento'] ?? ''),
                (string)($estadia['hospede_telefone'] ?? '')
            );
            foreach (listar_acompanhantes_estadia($pdo, $estadiaIdPost) as $item) {
                $vinculados[] = (int)$item['pessoa_id'];
                $chavesUsadas[] = $normalizarChave(
                    (string)$item['nome'],
                    (string)($item['documento'] ?? ''),
                    (string)($item['telefone'] ?? '')
                );
            }

            $total = 0;
            $idsExistentes = $_POST['acompanhante_existente_id'] ?? [];
            if (is_array($idsExistentes)) {
                foreach ($idsExistentes as $idBruto) {
                    $idPessoa = (int)$idBruto;
                    if ($idPessoa <= 0 || $idPessoa === (int)$estadia['hospede_principal_id'] || in_array($idPessoa, $vinculados, true)) {
                        continue;
                    }
                    $pessoaExistente = buscar_pessoa_por_id($pdo, $idPessoa);
                    if (!$pessoaExistente) {
                        continue;
                    }
                    $chavePessoa = $normalizarChave(
                        (string)$pessoaExistente['nome'],
                        (string)($pessoaExistente['documento'] ?? ''),
                        (string)($pessoaExistente['telefone'] ?? '')
                    );
                    if (in_array($chavePessoa, $chavesUsadas, true)) {
                        continue;
                    }
                    $stmt = $pdo->prepare('INSERT INTO estadia_acompanhantes (estadia_id, pessoa_id) VALUES (:estadia_id, :pessoa_id)');
                    $stmt->execute(['estadia_id' => $estadiaIdPost, 'pessoa_id' => $idPessoa]);
                    $vinculados[] = $idPessoa;
                    $chavesUsadas[] = $chavePessoa;
                    $total++;
                }
            }

            $nomes = $_POST['acompanhante_nome'] ?? [];
            $docs = $_POST['acompanhante_documento'] ?? [];
            $tels = $_POST['acompanhante_telefone'] ?? [];
            if (is_array($nomes) && is_array($docs) && is_array($tels)) {
                foreach ($nomes as $i => $nomeBruto) {
                    $nome = trim((string)$nomeBruto);
                    if ($nome === '') {
                        continue;
                    }
                    $documento = (string)($docs[$i] ?? '');
                    $telefone = (string)($tels[$i] ?? '');
                    $chaveEntrada = $normalizarChave($nome, $documento, $telefone);
                    if (in_array($chaveEntrada, $chavesUsadas, true)) {
                        continue;
                    }
                    $idPessoa = obter_ou_criar_pessoa($pdo, $nome, $documento, $telefone);
                    if ($idPessoa === (int)$estadia['hospede_principal_id'] || in_array($idPessoa, $vinculados, true)) {
                        continue;
                    }
                    $stmt = $pdo->prepare('INSERT INTO estadia_acompanhantes (estadia_id, pessoa_id) VALUES (:estadia_id, :pessoa_id)');
                    $stmt->execute(['estadia_id' => $estadiaIdPost, 'pessoa_id' => $idPessoa]);
                    $vinculados[] = $idPessoa;
                    $chavesUsadas[] = $chaveEntrada;
                    $total++;
                }
            }

            registrar_log($pdo, 'adicionar_acompanhantes', "Estadia {$estadiaIdPost} / {$total} adicionados");
            definir_flash('sucesso', "{$total} acompanhante(s) adicionados.");
            redirecionar('/portal/quarto.php?id=' . $quartoId . '&estadia_id=' . $estadiaIdPost);
        }

        if ($acao === 'remover_acompanhante') {
            $relacaoId = (int)($_POST['acompanhante_rel_id'] ?? 0);
            if ($relacaoId <= 0) {
                throw new RuntimeException('Acompanhante inválido.');
            }
            $stmtValida = $pdo->prepare('
                SELECT ea.id
                FROM estadia_acompanhantes ea
                INNER JOIN estadias e ON e.id = ea.estadia_id
                WHERE ea.id = :id
                  AND ea.estadia_id = :estadia_id
                  AND e.quarto_id = :quarto_id
                LIMIT 1
            ');
            $stmtValida->execute([
                'id' => $relacaoId,
                'estadia_id' => $estadiaIdPost,
                'quarto_id' => $quartoId,
            ]);
            if (!$stmtValida->fetch()) {
                throw new RuntimeException('Acompanhante não encontrado para esta estadia.');
            }

            $stmt = $pdo->prepare('DELETE FROM estadia_acompanhantes WHERE id = :id');
            $stmt->execute(['id' => $relacaoId]);
            registrar_log($pdo, 'remover_acompanhante', "Relação {$relacaoId} removida");
            definir_flash('sucesso', 'Acompanhante removido.');
            redirecionar('/portal/quarto.php?id=' . $quartoId . '&estadia_id=' . $estadiaIdPost);
        }

        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        definir_flash('erro', $erro->getMessage());
        redirecionar('/portal/quarto.php?id=' . $quartoId . ($estadiaIdPost > 0 ? '&estadia_id=' . $estadiaIdPost : ''));
    }
}

$flash = consumir_flash();
$estadiasAtivas = listar_estadias_ativas_quarto($pdo, (int)$quartoId);
$estadiaSelecionadaId = (int)($_GET['estadia_id'] ?? 0);
$estadiaSelecionada = $estadiasAtivas[0] ?? null;
foreach ($estadiasAtivas as $item) {
    if ((int)$item['id'] === $estadiaSelecionadaId) {
        $estadiaSelecionada = $item;
        break;
    }
}
$acompanhantes = $estadiaSelecionada ? listar_acompanhantes_estadia($pdo, (int)$estadiaSelecionada['id']) : [];
$pessoas = listar_pessoas_para_select($pdo, (string)($_GET['busca_pessoa'] ?? ''), 200);
$agora = new DateTimeImmutable('now');
$checkinBase = new DateTimeImmutable('today 12:00');
if ($agora > $checkinBase) {
    $checkinBase = new DateTimeImmutable('tomorrow 12:00');
}
$checkinPadrao = $checkinBase->format('Y-m-d\TH:i');
$checkoutPadrao = $checkinBase->modify('+1 day')->format('Y-m-d\TH:i');
$diariasPadrao = 1;

$diariasSelecionada = 1;
$valorDiariaSelecionada = '';
$formaPagamentoSelecionada = '';
if ($estadiaSelecionada) {
    $diariasSelecionada = (int)($estadiaSelecionada['quantidade_diarias'] ?? 0);
    if ($diariasSelecionada <= 0) {
        $diariasSelecionada = calcular_quantidade_diarias(
            (string)$estadiaSelecionada['data_checkin_previsto'],
            (string)$estadiaSelecionada['data_checkout_previsto']
        );
    }
    $valorDiariaSelecionada = $estadiaSelecionada['valor_diaria'] !== null
        ? number_format((float)$estadiaSelecionada['valor_diaria'], 2, ',', '.')
        : '';
    $formaPagamentoSelecionada = (string)($estadiaSelecionada['forma_pagamento'] ?? '');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quarto <?= e($quarto['numero']) ?> - <?= e(APP_NOME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/portal/assets/admin.css">
</head>
<body>
  <main class="admin-page">
    <header class="topbar">
      <div>
        <h1>Quarto <?= e($quarto['numero']) ?> - <?= e($quarto['tipo']) ?></h1>
        <small>Diária padrão: 12:00 até 12:00 do dia seguinte</small>
      </div>
      <div class="topbar-actions">
        <a class="btn btn-neutral" href="/portal/index.php">Dashboard</a>
        <a class="btn btn-neutral" href="/portal/pessoas.php">Cadastro de hóspedes</a>
        <a class="btn btn-neutral" href="/portal/historico.php">Histórico</a>
        <?php if (usuario_eh_master()): ?>
          <a class="btn btn-neutral" href="/portal/usuarios.php">Usuários</a>
        <?php endif; ?>
        <form method="post" action="/portal/logout.php" class="inline-form"><?= csrf_input() ?><button class="btn btn-primary" type="submit">Sair</button></form>
      </div>
    </header>

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['tipo']) ?>" data-flash><?= e($flash['mensagem']) ?></div><?php endif; ?>

    <section class="card">
      <div class="grid-3">
        <div><label>Status</label><span class="badge <?= e(classe_status_quarto($quarto['status'])) ?>"><?= e($quarto['status']) ?></span></div>
        <div><label>Banheiro</label><div><?= e($quarto['banheiro']) ?></div></div>
        <div><label>Camas</label><div><?= e($quarto['camas']) ?></div></div>
      </div>
      <div class="acoes" style="margin-top:10px;">
        <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="marcar_hospedado"><button class="btn btn-danger" type="submit">Ocupado</button></form>
        <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="marcar_reservado"><button class="btn btn-warning" type="submit">Reservado</button></form>
        <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="marcar_limpeza"><button class="btn btn-primary" type="submit">Limpeza</button></form>
        <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="marcar_manutencao"><button class="btn btn-manut" type="submit">Manutenção</button></form>
        <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="marcar_livre"><button class="btn btn-success" type="submit">Livre</button></form>
      </div>
    </section>

    <section class="card no-print card-estadia-form">
      <h2>Criar estadia</h2>
      <form method="post">
        <?= csrf_input() ?><input type="hidden" name="acao" value="criar_estadia">
        <div class="grid-3 form-estadia-grid">
          <div class="campo-prioritario campo-cadastrados"><label>Banco de Clientes já Cadastrados</label><select name="hospede_principal_id"><option value="0">Selecionar...</option><?php foreach ($pessoas as $p): ?><option value="<?= e((string)$p['id']) ?>"><?= e($p['nome']) ?> - <?= e($p['telefone'] ?: '-') ?></option><?php endforeach; ?></select></div>
          <div><label>Nome rápido</label><input name="hospede_nome"></div>
          <div><label>Documento</label><input name="hospede_documento"></div>
          <div><label>Telefone</label><input name="hospede_telefone"></div>
          <div><label>Email</label><input name="hospede_email" type="email"></div>
          <div class="campo-prioritario"><label>Status inicial</label><select name="status_inicial"><option value="reservada">Reservada</option><option value="hospedado">Ocupado</option></select></div>
          <div class="campo-prioritario campo-checkin"><label>Check-in</label><input type="datetime-local" name="data_checkin_previsto" value="<?= e($checkinPadrao) ?>" required></div>
          <div class="campo-prioritario campo-checkout"><label>Check-out</label><input type="datetime-local" name="data_checkout_previsto" value="<?= e($checkoutPadrao) ?>" required></div>
          <div class="campo-prioritario"><label>Quantidade de diárias</label><input type="number" name="quantidade_diarias" min="1" value="<?= e((string)$diariasPadrao) ?>" required></div>
          <div><label>Valor da diária (R$)</label><input name="valor_diaria" placeholder="Ex.: 120,00"></div>
          <div>
            <label>Forma de pagamento</label>
            <select name="forma_pagamento">
              <option value="">Não informado</option>
              <option value="Dinheiro">Dinheiro</option>
              <option value="PIX">PIX</option>
              <option value="Cartão de débito">Cartão de débito</option>
              <option value="Cartão de crédito">Cartão de crédito</option>
              <option value="Transferência">Transferência</option>
              <option value="Outro">Outro</option>
            </select>
          </div>
          <div><label>Observações</label><input name="observacoes"></div>
        </div>
        <div style="margin-top:10px;"><button class="btn btn-primary" type="submit">Criar estadia</button></div>
      </form>
    </section>

    <section class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
        <h2 style="margin:0;">Estadias ativas</h2>
        <button class="btn btn-success no-print" type="button" onclick="window.print()">Imprimir reservas do quarto</button>
      </div>
      <div class="lista-estadias">
        <?php if (!$estadiasAtivas): ?><p class="texto-mudo">Sem estadias ativas.</p><?php endif; ?>
        <?php foreach ($estadiasAtivas as $e): ?>
          <article class="estadia-item <?= $estadiaSelecionada && (int)$estadiaSelecionada['id'] === (int)$e['id'] ? 'ativa' : '' ?>">
            <div>
              <strong><?= e($e['hospede_nome']) ?></strong><br>
              <span class="texto-mudo"><?= e(formatar_data($e['data_checkin_previsto'])) ?> até <?= e(formatar_data($e['data_checkout_previsto'])) ?></span><br>
              <span class="texto-mudo">
                Diárias: <?= e((string)($e['quantidade_diarias'] ?? '-')) ?>
                <?php if (($e['valor_diaria'] ?? null) !== null): ?>
                  | Diária: R$ <?= e(number_format((float)$e['valor_diaria'], 2, ',', '.')) ?>
                <?php endif; ?>
                <?php if (!empty($e['forma_pagamento'])): ?>
                  | Pgto: <?= e($e['forma_pagamento']) ?>
                <?php endif; ?>
              </span>
            </div>
            <div style="display:flex; gap:8px; align-items:center;"><span class="badge <?= e(classe_status_estadia($e['status'])) ?>"><?= e($e['status']) ?></span><a class="btn btn-neutral" href="/portal/quarto.php?id=<?= e((string)$quartoId) ?>&estadia_id=<?= e((string)$e['id']) ?>">Abrir</a></div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($estadiaSelecionada): ?>
      <section class="card no-print card-estadia-form">
        <h2>Editar estadia #<?= e((string)$estadiaSelecionada['id']) ?></h2>
        <form method="post">
          <?= csrf_input() ?><input type="hidden" name="acao" value="editar_estadia"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>">
          <div class="grid-3 form-estadia-grid">
            <div class="campo-prioritario campo-cadastrados"><label>Banco de Clientes já Cadastrados</label><select name="hospede_principal_id"><option value="0">Selecionar...</option><?php foreach ($pessoas as $p): ?><option value="<?= e((string)$p['id']) ?>" <?= (int)$p['id'] === (int)$estadiaSelecionada['hospede_principal_id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> - <?= e($p['telefone'] ?: '-') ?></option><?php endforeach; ?></select></div>
            <div><label>Nome rápido</label><input name="hospede_nome" value="<?= e($estadiaSelecionada['hospede_nome']) ?>"></div>
            <div><label>Documento</label><input name="hospede_documento" value="<?= e($estadiaSelecionada['hospede_documento']) ?>"></div>
            <div><label>Telefone</label><input name="hospede_telefone" value="<?= e($estadiaSelecionada['hospede_telefone']) ?>"></div>
            <div class="campo-prioritario campo-checkin"><label>Check-in</label><input type="datetime-local" name="data_checkin_previsto" value="<?= e(formatar_para_datetime_local($estadiaSelecionada['data_checkin_previsto'])) ?>" required></div>
            <div class="campo-prioritario campo-checkout"><label>Check-out</label><input type="datetime-local" name="data_checkout_previsto" value="<?= e(formatar_para_datetime_local($estadiaSelecionada['data_checkout_previsto'])) ?>" required></div>
            <div class="campo-prioritario"><label>Quantidade de diárias</label><input type="number" name="quantidade_diarias" min="1" value="<?= e((string)$diariasSelecionada) ?>" required></div>
            <div><label>Valor da diária (R$)</label><input name="valor_diaria" value="<?= e($valorDiariaSelecionada) ?>" placeholder="Ex.: 120,00"></div>
            <div>
              <label>Forma de pagamento</label>
              <select name="forma_pagamento">
                <option value="" <?= $formaPagamentoSelecionada === '' ? 'selected' : '' ?>>Não informado</option>
                <option value="Dinheiro" <?= $formaPagamentoSelecionada === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                <option value="PIX" <?= $formaPagamentoSelecionada === 'PIX' ? 'selected' : '' ?>>PIX</option>
                <option value="Cartão de débito" <?= $formaPagamentoSelecionada === 'Cartão de débito' ? 'selected' : '' ?>>Cartão de débito</option>
                <option value="Cartão de crédito" <?= $formaPagamentoSelecionada === 'Cartão de crédito' ? 'selected' : '' ?>>Cartão de crédito</option>
                <option value="Transferência" <?= $formaPagamentoSelecionada === 'Transferência' ? 'selected' : '' ?>>Transferência</option>
                <option value="Outro" <?= $formaPagamentoSelecionada === 'Outro' ? 'selected' : '' ?>>Outro</option>
              </select>
            </div>
            <div><label>Observações</label><input name="observacoes" value="<?= e($estadiaSelecionada['observacoes']) ?>"></div>
          </div>
          <div class="acoes" style="margin-top:10px;"><button class="btn btn-primary" type="submit">Salvar edição</button></div>
        </form>
        <div class="acoes" style="margin-top:10px;">
          <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="confirmar_checkin"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>"><button class="btn btn-success" type="submit">Confirmar check-in</button></form>
          <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="check_out"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>"><button class="btn btn-warning" type="submit">Check-out</button></form>
          <form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="cancelar_reserva"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>"><button class="btn btn-danger" type="submit">Cancelar</button></form>
        </div>
      </section>

      <section class="card no-print">
        <h2>Acompanhantes</h2>
        <form method="post">
          <?= csrf_input() ?><input type="hidden" name="acao" value="adicionar_acompanhantes"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>">
          <div class="bloco-acompanhante bloco-existente">
            <label for="filtro-acompanhantes">Banco de Clientes já Cadastrados</label>
            <input id="filtro-acompanhantes" type="text" placeholder="Digite parte do nome..." data-select-filter="select-acompanhantes" list="lista-acompanhantes" autocomplete="off">
            <datalist id="lista-acompanhantes">
              <?php foreach ($pessoas as $p): ?>
                <option value="<?= e($p['nome']) ?>"><?= e($p['nome']) ?> - <?= e($p['telefone'] ?: '-') ?></option>
              <?php endforeach; ?>
            </datalist>

            <div style="margin-top:10px;">
              <label for="select-acompanhantes">Hóspedes cadastrados (seleção múltipla)</label>
              <select id="select-acompanhantes" name="acompanhante_existente_id[]" multiple size="5">
                <?php foreach ($pessoas as $p): ?>
                  <option value="<?= e((string)$p['id']) ?>"><?= e($p['nome']) ?> - <?= e($p['telefone'] ?: '-') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="bloco-acompanhante bloco-novo">
            <h3>Cadastrar novo cliente/acompanhante</h3>
            <div id="acompanhantes-dinamicos"><div class="acompanhante-linha"><div><label>Nome</label><input name="acompanhante_nome[]"></div><div><label>Documento</label><input name="acompanhante_documento[]"></div><div><label>Telefone</label><input name="acompanhante_telefone[]"></div></div></div>
          </div>
          <template id="template-acompanhante"><div class="acompanhante-linha"><div><label>Nome</label><input name="acompanhante_nome[]"></div><div><label>Documento</label><input name="acompanhante_documento[]"></div><div><label>Telefone</label><input name="acompanhante_telefone[]"></div><button class="btn btn-danger" type="button" data-remover-linha>Remover esta linha</button></div></template>
          <div class="acoes" style="margin-top:10px;">
            <button id="btn-add-acompanhante" class="btn btn-add-linha" type="button">Adicionar +1 linha de acompanhante</button>
            <button class="btn btn-add-quarto" type="submit">Adicionar acompanhantes ao quarto</button>
          </div>
        </form>
        <div style="margin-top:12px;"><?php foreach ($acompanhantes as $a): ?><div class="acompanhante-item"><div><strong><?= e($a['nome']) ?></strong><br><span class="texto-mudo">Doc: <?= e($a['documento'] ?: '-') ?> | Tel: <?= e($a['telefone'] ?: '-') ?></span></div><form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="acao" value="remover_acompanhante"><input type="hidden" name="estadia_id" value="<?= e((string)$estadiaSelecionada['id']) ?>"><input type="hidden" name="acompanhante_rel_id" value="<?= e((string)$a['id']) ?>"><button class="btn btn-danger" type="submit">Remover</button></form></div><?php endforeach; ?></div>
      </section>
    <?php endif; ?>
  </main>
  <script src="/portal/assets/admin.js"></script>
</body>
</html>
