<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function registrar_erro_conexao_bd(string $contexto, Throwable $erro): void
{
    error_log("[BD][CONEXAO][{$contexto}] " . $erro->getMessage());
}

/**
 * Garante colunas mínimas para compatibilidade com base já existente.
 */
function aplicar_migracoes_minimas(PDO $pdo): void
{
    static $aplicado = false;
    if ($aplicado) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL AFTER nome");
        $tabelaUsuariosExiste = (bool)$pdo->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
        if ($tabelaUsuariosExiste) {
            // Converte bases antigas (recepcao) para o modelo atual (master/admin).
            $pdo->exec("UPDATE usuarios SET nivel = 'admin' WHERE nivel NOT IN ('admin', 'master')");
            $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('master', 'admin') NOT NULL DEFAULT 'admin'");
        }
        $pdo->exec("ALTER TABLE estadias ADD COLUMN IF NOT EXISTS quantidade_diarias INT UNSIGNED NOT NULL DEFAULT 1 AFTER data_checkout_previsto");
        $pdo->exec("ALTER TABLE estadias ADD COLUMN IF NOT EXISTS valor_diaria DECIMAL(10,2) NULL AFTER quantidade_diarias");
        $pdo->exec("ALTER TABLE estadias ADD COLUMN IF NOT EXISTS forma_pagamento VARCHAR(50) NULL AFTER valor_diaria");

        $aplicado = true;
    } catch (Throwable $erro) {
        registrar_erro_conexao_bd('aplicar_migracoes_minimas', $erro);
        throw new RuntimeException('Falha ao aplicar migrações mínimas do banco.');
    }
}

/**
 * Retorna instância única de conexão PDO.
 */
function obter_conexao(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NOME,
        DB_CHARSET
    );

    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, $opcoes);
        aplicar_migracoes_minimas($pdo);
        return $pdo;
    } catch (Throwable $erro) {
        registrar_erro_conexao_bd('obter_conexao', $erro);
        if (!headers_sent()) {
            http_response_code(503);
        }
        exit('Serviço temporariamente indisponível por falha de comunicação com o banco de dados.');
    }
}
