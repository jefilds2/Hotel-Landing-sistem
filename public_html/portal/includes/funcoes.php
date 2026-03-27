<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function registrar_erro_bd(string $contexto, Throwable $erro): void
{
    error_log("[BD][{$contexto}] " . $erro->getMessage());
}

function registrar_log(PDO $pdo, string $acao, string $detalhes = ''): void
{
    try {
        $usuarioId = $_SESSION['usuario']['id'] ?? null;
        $ip = obter_ip_cliente();

        $sql = 'INSERT INTO auditoria_logs (usuario_id, acao, detalhes, ip) VALUES (:usuario_id, :acao, :detalhes, :ip)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'detalhes' => $detalhes,
            'ip' => $ip,
        ]);
    } catch (Throwable $erro) {
        registrar_erro_bd('registrar_log', $erro);
    }
}

function normalizar_data_hora_estadia(string $valor): string|null
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    $formatos = [
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
    ];

    foreach ($formatos as $formato) {
        $dt = DateTimeImmutable::createFromFormat($formato, $valor);
        if (!$dt) {
            continue;
        }

        if ($formato === 'Y-m-d') {
            return $dt->format('Y-m-d') . ' 12:00:00';
        }

        return $dt->format('Y-m-d H:i:s');
    }

    return null;
}

function normalizar_periodo_estadia(string $checkin, string $checkout): array
{
    $checkinNormalizado = normalizar_data_hora_estadia($checkin);
    $checkoutNormalizado = normalizar_data_hora_estadia($checkout);

    if ($checkinNormalizado === null || $checkoutNormalizado === null) {
        return [false, 'Informe data e hora válidas no padrão da diária.', null, null];
    }

    $inicio = new DateTimeImmutable($checkinNormalizado);
    $fim = new DateTimeImmutable($checkoutNormalizado);
    if ($inicio >= $fim) {
        return [false, 'A data/hora de check-out deve ser posterior ao check-in.', null, null];
    }

    return [true, 'ok', $checkinNormalizado, $checkoutNormalizado];
}

function calcular_quantidade_diarias(string $checkin, string $checkout): int
{
    $inicio = new DateTimeImmutable($checkin);
    $fim = new DateTimeImmutable($checkout);
    $segundos = max(0, $fim->getTimestamp() - $inicio->getTimestamp());
    $diarias = (int)ceil($segundos / 86400);
    return max(1, $diarias);
}

function normalizar_valor_monetario(string|null $valor): float|null
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }

    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    if (!is_numeric($valor)) {
        throw new InvalidArgumentException('Valor da diária inválido.');
    }

    $numero = (float)$valor;
    if ($numero < 0) {
        throw new InvalidArgumentException('Valor da diária não pode ser negativo.');
    }

    return round($numero, 2);
}

function validar_periodo_estadia(string $checkin, string $checkout): array
{
    [$ok, $mensagem] = normalizar_periodo_estadia($checkin, $checkout);
    return [$ok, $mensagem];
}

function existe_conflito_reserva(
    PDO $pdo,
    int $quartoId,
    string $checkin,
    string $checkout,
    int|null $ignorarEstadiaId = null
): bool {
    try {
        $sql = '
            SELECT COUNT(*) AS total
            FROM estadias
            WHERE quarto_id = :quarto_id
              AND status IN (\'reservada\', \'hospedado\')
              AND data_checkout_previsto > data_checkin_previsto
              AND data_checkin_previsto < :checkout
              AND data_checkout_previsto > :checkin
        ';

        $params = [
            'quarto_id' => $quartoId,
            'checkin' => $checkin,
            'checkout' => $checkout,
        ];

        if ($ignorarEstadiaId !== null) {
            $sql .= ' AND id <> :ignorar_estadia_id';
            $params['ignorar_estadia_id'] = $ignorarEstadiaId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetch();

        return ((int)($resultado['total'] ?? 0)) > 0;
    } catch (Throwable $erro) {
        registrar_erro_bd('existe_conflito_reserva', $erro);
        return false;
    }
}

function criar_pessoa(
    PDO $pdo,
    string $nome,
    string|null $documento = null,
    string|null $telefone = null,
    string|null $email = null
): int {
    try {
        $nome = trim($nome);
        $documento = trim((string)$documento);
        $telefone = trim((string)$telefone);
        $email = trim((string)$email);

        if ($nome === '') {
            throw new InvalidArgumentException('Nome da pessoa é obrigatório.');
        }

        $sqlInsert = '
            INSERT INTO pessoas (nome, documento, telefone, email)
            VALUES (:nome, :documento, :telefone, :email)
        ';
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            'nome' => $nome,
            'documento' => $documento !== '' ? $documento : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'email' => $email !== '' ? $email : null,
        ]);

        return (int)$pdo->lastInsertId();
    } catch (Throwable $erro) {
        registrar_erro_bd('criar_pessoa', $erro);
        throw new RuntimeException('Falha ao salvar pessoa no banco de dados.');
    }
}

function atualizar_pessoa(
    PDO $pdo,
    int $pessoaId,
    string $nome,
    string|null $documento = null,
    string|null $telefone = null,
    string|null $email = null
): void {
    try {
        $nome = trim($nome);
        $documento = trim((string)$documento);
        $telefone = trim((string)$telefone);
        $email = trim((string)$email);

        if ($nome === '') {
            throw new InvalidArgumentException('Nome da pessoa é obrigatório.');
        }

        $stmt = $pdo->prepare('
            UPDATE pessoas
            SET nome = :nome,
                documento = :documento,
                telefone = :telefone,
                email = :email
            WHERE id = :id
        ');
        $stmt->execute([
            'nome' => $nome,
            'documento' => $documento !== '' ? $documento : null,
            'telefone' => $telefone !== '' ? $telefone : null,
            'email' => $email !== '' ? $email : null,
            'id' => $pessoaId,
        ]);
    } catch (Throwable $erro) {
        registrar_erro_bd('atualizar_pessoa', $erro);
        throw new RuntimeException('Falha ao atualizar pessoa no banco de dados.');
    }
}

function obter_ou_criar_pessoa(
    PDO $pdo,
    string $nome,
    string|null $documento = null,
    string|null $telefone = null,
    string|null $email = null
): int {
    try {
        $nome = trim($nome);
        $documento = trim((string)$documento);

        if ($nome === '') {
            throw new InvalidArgumentException('Nome da pessoa é obrigatório.');
        }

        if ($documento !== '') {
            $stmtBusca = $pdo->prepare('SELECT id FROM pessoas WHERE documento = :documento LIMIT 1');
            $stmtBusca->execute(['documento' => $documento]);
            $pessoa = $stmtBusca->fetch();
            if ($pessoa) {
                return (int)$pessoa['id'];
            }
        }

        return criar_pessoa($pdo, $nome, $documento, $telefone, $email);
    } catch (Throwable $erro) {
        registrar_erro_bd('obter_ou_criar_pessoa', $erro);
        throw new RuntimeException('Falha ao resolver cadastro da pessoa.');
    }
}

function buscar_pessoa_por_id(PDO $pdo, int $pessoaId): array|null
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM pessoas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $pessoaId]);
        $pessoa = $stmt->fetch();

        return $pessoa ?: null;
    } catch (Throwable $erro) {
        registrar_erro_bd('buscar_pessoa_por_id', $erro);
        return null;
    }
}

function pessoa_possui_vinculos(PDO $pdo, int $pessoaId): bool
{
    try {
        $stmtEstadias = $pdo->prepare('SELECT COUNT(*) AS total FROM estadias WHERE hospede_principal_id = :pessoa_id');
        $stmtEstadias->execute(['pessoa_id' => $pessoaId]);
        $totalEstadias = (int)($stmtEstadias->fetch()['total'] ?? 0);

        $stmtAcompanhante = $pdo->prepare('SELECT COUNT(*) AS total FROM estadia_acompanhantes WHERE pessoa_id = :pessoa_id');
        $stmtAcompanhante->execute(['pessoa_id' => $pessoaId]);
        $totalAcompanhante = (int)($stmtAcompanhante->fetch()['total'] ?? 0);

        return ($totalEstadias + $totalAcompanhante) > 0;
    } catch (Throwable $erro) {
        registrar_erro_bd('pessoa_possui_vinculos', $erro);
        return false;
    }
}

function excluir_pessoa(PDO $pdo, int $pessoaId): void
{
    try {
        $pdo->beginTransaction();
        $stmtPessoa = $pdo->prepare('SELECT id FROM pessoas WHERE id = :id LIMIT 1');
        $stmtPessoa->execute(['id' => $pessoaId]);
        if (!$stmtPessoa->fetch()) {
            throw new RuntimeException('Pessoa não encontrada para exclusão.');
        }

        $stmtQuartosAfetados = $pdo->prepare('
            SELECT DISTINCT quarto_id
            FROM estadias
            WHERE hospede_principal_id = :pessoa_id
        ');
        $stmtQuartosAfetados->execute(['pessoa_id' => $pessoaId]);
        $quartosAfetados = array_map(
            static fn(array $linha): int => (int)$linha['quarto_id'],
            $stmtQuartosAfetados->fetchAll()
        );

        // Remove vínculos em estadias de outros hóspedes onde esta pessoa era acompanhante.
        $stmtDeleteAcompDireto = $pdo->prepare('DELETE FROM estadia_acompanhantes WHERE pessoa_id = :pessoa_id');
        $stmtDeleteAcompDireto->execute(['pessoa_id' => $pessoaId]);

        // Remove histórico onde a pessoa era hóspede principal.
        $stmtDeleteEstadias = $pdo->prepare('DELETE FROM estadias WHERE hospede_principal_id = :pessoa_id');
        $stmtDeleteEstadias->execute(['pessoa_id' => $pessoaId]);

        $stmtDeletePessoa = $pdo->prepare('DELETE FROM pessoas WHERE id = :id');
        $stmtDeletePessoa->execute(['id' => $pessoaId]);

        // Recalcula status dos quartos impactados por exclusão de estadias.
        foreach ($quartosAfetados as $quartoId) {
            $stmtAtiva = $pdo->prepare('
                SELECT status
                FROM estadias
                WHERE quarto_id = :quarto_id
                  AND status IN (\'reservada\', \'hospedado\')
                ORDER BY (status = \'hospedado\') DESC, data_checkin_previsto ASC
                LIMIT 1
            ');
            $stmtAtiva->execute(['quarto_id' => $quartoId]);
            $ativa = $stmtAtiva->fetch();

            if ($ativa && $ativa['status'] === 'hospedado') {
                atualizar_status_quarto($pdo, $quartoId, 'ocupado');
                continue;
            }

            if ($ativa && $ativa['status'] === 'reservada') {
                atualizar_status_quarto($pdo, $quartoId, 'reservado');
                continue;
            }

            $stmtStatusAtual = $pdo->prepare('SELECT status FROM quartos WHERE id = :id');
            $stmtStatusAtual->execute(['id' => $quartoId]);
            $statusAtual = (string)($stmtStatusAtual->fetch()['status'] ?? 'livre');

            if (in_array($statusAtual, ['reservado', 'ocupado'], true)) {
                atualizar_status_quarto($pdo, $quartoId, 'livre');
            }
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        registrar_erro_bd('excluir_pessoa', $erro);
        throw new RuntimeException('Falha ao excluir pessoa no banco de dados.');
    }
}

function listar_pessoas(PDO $pdo, string $busca = '', int $limite = 200): array
{
    try {
        $busca = trim($busca);
        $limite = max(1, min($limite, 500));

        if ($busca === '') {
            $stmt = $pdo->query("
                SELECT p.*, COUNT(e.id) AS total_estadias
                FROM pessoas p
                LEFT JOIN estadias e ON e.hospede_principal_id = p.id
                GROUP BY p.id
                ORDER BY p.nome ASC
                LIMIT {$limite}
            ");
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare("
            SELECT p.*, COUNT(e.id) AS total_estadias
            FROM pessoas p
            LEFT JOIN estadias e ON e.hospede_principal_id = p.id
            WHERE p.nome LIKE :busca_nome OR p.telefone LIKE :busca_telefone
            GROUP BY p.id
            ORDER BY p.nome ASC
            LIMIT {$limite}
        ");
        $stmt->execute([
            'busca_nome' => '%' . $busca . '%',
            'busca_telefone' => '%' . $busca . '%',
        ]);

        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_pessoas', $erro);
        return [];
    }
}

function listar_pessoas_para_select(PDO $pdo, string $busca = '', int $limite = 100): array
{
    try {
        $busca = trim($busca);
        $limite = max(1, min($limite, 300));

        if ($busca === '') {
            $stmt = $pdo->query("
                SELECT id, nome, documento, telefone
                FROM pessoas
                ORDER BY nome ASC
                LIMIT {$limite}
            ");
            return $stmt->fetchAll();
        }

        $stmt = $pdo->prepare("
            SELECT id, nome, documento, telefone
            FROM pessoas
            WHERE nome LIKE :busca_nome OR telefone LIKE :busca_telefone OR documento LIKE :busca_documento
            ORDER BY nome ASC
            LIMIT {$limite}
        ");
        $stmt->execute([
            'busca_nome' => '%' . $busca . '%',
            'busca_telefone' => '%' . $busca . '%',
            'busca_documento' => '%' . $busca . '%',
        ]);
        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_pessoas_para_select', $erro);
        return [];
    }
}

function buscar_quarto_por_id(PDO $pdo, int $quartoId): array|null
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM quartos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $quartoId]);
        $quarto = $stmt->fetch();

        return $quarto ?: null;
    } catch (Throwable $erro) {
        registrar_erro_bd('buscar_quarto_por_id', $erro);
        return null;
    }
}

function listar_estadias_ativas_quarto(PDO $pdo, int $quartoId): array
{
    try {
        $sql = '
            SELECT e.*, p.nome AS hospede_nome, p.documento AS hospede_documento, p.telefone AS hospede_telefone
            FROM estadias e
            INNER JOIN pessoas p ON p.id = e.hospede_principal_id
            WHERE e.quarto_id = :quarto_id
              AND e.status IN (\'reservada\', \'hospedado\')
            ORDER BY e.data_checkin_previsto ASC, e.id DESC
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['quarto_id' => $quartoId]);

        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_estadias_ativas_quarto', $erro);
        return [];
    }
}

function buscar_estadia_por_id(PDO $pdo, int $estadiaId): array|null
{
    try {
        $sql = '
            SELECT e.*, p.nome AS hospede_nome, p.documento AS hospede_documento, p.telefone AS hospede_telefone
            FROM estadias e
            INNER JOIN pessoas p ON p.id = e.hospede_principal_id
            WHERE e.id = :id
            LIMIT 1
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $estadiaId]);
        $estadia = $stmt->fetch();

        return $estadia ?: null;
    } catch (Throwable $erro) {
        registrar_erro_bd('buscar_estadia_por_id', $erro);
        return null;
    }
}

function listar_acompanhantes_estadia(PDO $pdo, int $estadiaId): array
{
    try {
        $sql = '
            SELECT ea.id, ea.pessoa_id, p.nome, p.documento, p.telefone
            FROM estadia_acompanhantes ea
            INNER JOIN pessoas p ON p.id = ea.pessoa_id
            WHERE ea.estadia_id = :estadia_id
            ORDER BY p.nome ASC
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estadia_id' => $estadiaId]);

        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_acompanhantes_estadia', $erro);
        return [];
    }
}

function listar_historico_pessoa(PDO $pdo, int $pessoaId, int $limite = 100): array
{
    try {
        $limite = max(1, min($limite, 300));
        $stmt = $pdo->prepare("
            SELECT
                e.id,
                q.numero AS quarto_numero,
                q.tipo AS quarto_tipo,
                e.status,
                e.data_checkin_previsto,
                e.data_checkout_previsto,
                e.data_checkin_real,
                e.data_checkout_real
            FROM estadias e
            INNER JOIN quartos q ON q.id = e.quarto_id
            WHERE e.hospede_principal_id = :pessoa_id
               OR EXISTS (
                   SELECT 1
                   FROM estadia_acompanhantes ea
                   WHERE ea.estadia_id = e.id
                     AND ea.pessoa_id = :pessoa_id2
               )
            ORDER BY e.id DESC
            LIMIT {$limite}
        ");
        $stmt->execute([
            'pessoa_id' => $pessoaId,
            'pessoa_id2' => $pessoaId,
        ]);

        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_historico_pessoa', $erro);
        return [];
    }
}

function listar_historico_estadias(
    PDO $pdo,
    string|null $inicio = null,
    string|null $fim = null,
    int|null $quartoId = null,
    int|null $pessoaId = null,
    string|null $pessoaBusca = null
): array {
    try {
        $sql = '
            SELECT
                e.id,
                e.status,
                e.data_checkin_previsto,
                e.data_checkout_previsto,
                e.quantidade_diarias,
                e.valor_diaria,
                e.forma_pagamento,
                e.data_checkin_real,
                e.data_checkout_real,
                e.observacoes,
                q.id AS quarto_id,
                q.numero AS quarto_numero,
                p.id AS hospede_id,
                p.nome AS hospede_nome
            FROM estadias e
            INNER JOIN quartos q ON q.id = e.quarto_id
            INNER JOIN pessoas p ON p.id = e.hospede_principal_id
            WHERE 1 = 1
        ';

        $params = [];
        if ($inicio !== null && $inicio !== '') {
            $sql .= ' AND e.data_checkin_previsto >= :inicio ';
            $params['inicio'] = $inicio;
        }
        if ($fim !== null && $fim !== '') {
            $sql .= ' AND e.data_checkin_previsto <= :fim ';
            $params['fim'] = $fim;
        }
        if ($quartoId !== null && $quartoId > 0) {
            $sql .= ' AND e.quarto_id = :quarto_id ';
            $params['quarto_id'] = $quartoId;
        }
        if ($pessoaId !== null && $pessoaId > 0) {
            $sql .= '
                AND (
                    e.hospede_principal_id = :pessoa_id
                    OR EXISTS (
                        SELECT 1
                        FROM estadia_acompanhantes ea
                        WHERE ea.estadia_id = e.id
                          AND ea.pessoa_id = :pessoa_id2
                    )
                )
            ';
            $params['pessoa_id'] = $pessoaId;
            $params['pessoa_id2'] = $pessoaId;
        }
        if ($pessoaBusca !== null && trim($pessoaBusca) !== '') {
            $sql .= '
                AND (
                    p.nome LIKE :pessoa_busca_nome
                    OR EXISTS (
                        SELECT 1
                        FROM estadia_acompanhantes ea2
                        INNER JOIN pessoas p2 ON p2.id = ea2.pessoa_id
                        WHERE ea2.estadia_id = e.id
                          AND p2.nome LIKE :pessoa_busca_acomp
                    )
                )
            ';
            $params['pessoa_busca_nome'] = '%' . trim($pessoaBusca) . '%';
            $params['pessoa_busca_acomp'] = '%' . trim($pessoaBusca) . '%';
        }

        $sql .= ' ORDER BY e.id DESC LIMIT 500 ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $erro) {
        registrar_erro_bd('listar_historico_estadias', $erro);
        return [];
    }
}

function atualizar_status_quarto(PDO $pdo, int $quartoId, string $status): void
{
    $statusValidos = ['livre', 'reservado', 'ocupado', 'limpeza', 'manutencao'];
    if (!in_array($status, $statusValidos, true)) {
        throw new InvalidArgumentException('Status de quarto inválido.');
    }

    try {
        $stmt = $pdo->prepare('UPDATE quartos SET status = :status WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'id' => $quartoId,
        ]);
    } catch (Throwable $erro) {
        registrar_erro_bd('atualizar_status_quarto', $erro);
        throw new RuntimeException('Falha ao atualizar status do quarto.');
    }
}

function quarto_tem_estadia_ativa(PDO $pdo, int $quartoId, int|null $ignorarEstadiaId = null): bool
{
    try {
        $sql = '
            SELECT COUNT(*) AS total
            FROM estadias
            WHERE quarto_id = :quarto_id
              AND status IN (\'reservada\', \'hospedado\')
        ';

        $params = ['quarto_id' => $quartoId];
        if ($ignorarEstadiaId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignorarEstadiaId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return ((int)($result['total'] ?? 0)) > 0;
    } catch (Throwable $erro) {
        registrar_erro_bd('quarto_tem_estadia_ativa', $erro);
        return false;
    }
}

function formatar_data(string|null $data): string
{
    if (!$data) {
        return '-';
    }

    $dt = new DateTimeImmutable($data);
    return $dt->format('d/m/Y H:i');
}

function formatar_para_datetime_local(string|null $data): string
{
    if (!$data) {
        return '';
    }

    $dt = new DateTimeImmutable($data);
    return $dt->format('Y-m-d\TH:i');
}

function classe_status_quarto(string $status): string
{
    return match ($status) {
        'livre' => 'status-livre',
        'reservado' => 'status-reservado',
        'ocupado' => 'status-ocupado',
        'limpeza' => 'status-limpeza',
        'manutencao' => 'status-manutencao',
        default => 'status-default',
    };
}

function classe_status_estadia(string $status): string
{
    return match ($status) {
        'reservada' => 'status-reservado',
        'hospedado' => 'status-ocupado',
        'finalizada' => 'status-livre',
        'cancelada' => 'status-manutencao',
        default => 'status-default',
    };
}
