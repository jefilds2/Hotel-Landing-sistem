-- Estrutura do banco Hotel Bela Vista
CREATE DATABASE IF NOT EXISTS hotel_bela_vista
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hotel_bela_vista;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  usuario VARCHAR(60) NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  nivel ENUM('master', 'admin') NOT NULL DEFAULT 'admin',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quartos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(10) NOT NULL UNIQUE,
  tipo ENUM('Simples', 'Casal') NOT NULL,
  banheiro VARCHAR(60) NOT NULL,
  camas VARCHAR(60) NOT NULL,
  status ENUM('livre', 'reservado', 'ocupado', 'limpeza', 'manutencao') NOT NULL DEFAULT 'livre',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pessoas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  documento VARCHAR(40) NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pessoas_documento (documento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estadias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quarto_id INT UNSIGNED NOT NULL,
  hospede_principal_id INT UNSIGNED NOT NULL,
  data_checkin_previsto DATETIME NOT NULL,
  data_checkout_previsto DATETIME NOT NULL,
  quantidade_diarias INT UNSIGNED NOT NULL DEFAULT 1,
  valor_diaria DECIMAL(10,2) NULL,
  forma_pagamento VARCHAR(50) NULL,
  data_checkin_real DATETIME NULL,
  data_checkout_real DATETIME NULL,
  status ENUM('reservada', 'hospedado', 'finalizada', 'cancelada') NOT NULL DEFAULT 'reservada',
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_estadias_quarto FOREIGN KEY (quarto_id) REFERENCES quartos(id),
  CONSTRAINT fk_estadias_principal FOREIGN KEY (hospede_principal_id) REFERENCES pessoas(id),
  INDEX idx_estadias_quarto_status (quarto_id, status),
  INDEX idx_estadias_periodo (data_checkin_previsto, data_checkout_previsto)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estadia_acompanhantes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estadia_id INT UNSIGNED NOT NULL,
  pessoa_id INT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acomp_estadia FOREIGN KEY (estadia_id) REFERENCES estadias(id) ON DELETE CASCADE,
  CONSTRAINT fk_acomp_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id),
  UNIQUE KEY uq_estadia_pessoa (estadia_id, pessoa_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_tentativas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login_nome VARCHAR(120) NOT NULL,
  ip VARCHAR(60) NOT NULL,
  tentativas INT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_login_ip (login_nome, ip)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auditoria_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  acao VARCHAR(120) NOT NULL,
  detalhes TEXT NULL,
  ip VARCHAR(60) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_usuario (usuario_id),
  INDEX idx_logs_data (criado_em)
) ENGINE=InnoDB;
