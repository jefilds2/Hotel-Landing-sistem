USE hotel_bela_vista;

-- Compatibilidade com bases antigas (sem coluna email em usuarios)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL AFTER nome;

-- Compatibilidade de cargos antigos para o modelo atual
UPDATE usuarios
SET nivel = 'admin'
WHERE nivel NOT IN ('admin', 'master');

ALTER TABLE usuarios
  MODIFY COLUMN nivel ENUM('master', 'admin') NOT NULL DEFAULT 'admin';

-- Normaliza a base para manter somente os quartos oficiais do Hotel Bela Vista
DELETE ea
FROM estadia_acompanhantes ea
INNER JOIN estadias e ON e.id = ea.estadia_id
INNER JOIN quartos q ON q.id = e.quarto_id
WHERE q.numero NOT IN ('0','1','2','3','4','5','6','7','8','9','12','13','14','15','16','17','18','19','20');

DELETE e
FROM estadias e
INNER JOIN quartos q ON q.id = e.quarto_id
WHERE q.numero NOT IN ('0','1','2','3','4','5','6','7','8','9','12','13','14','15','16','17','18','19','20');

DELETE FROM quartos
WHERE numero NOT IN ('0','1','2','3','4','5','6','7','8','9','12','13','14','15','16','17','18','19','20');

-- Quartos oficiais informados pelo hotel (0-20 conforme especificação)
INSERT INTO quartos (numero, tipo, banheiro, camas, status) VALUES
('0', 'Casal', 'Sem banheiro', '1 cama casal', 'livre'),
('1', 'Simples', 'Sem banheiro', '2 camas', 'livre'),
('2', 'Simples', 'Sem banheiro', '3 camas', 'livre'),
('3', 'Simples', 'Sem banheiro', '2 camas', 'livre'),
('4', 'Simples', 'Sem banheiro', '1 cama', 'livre'),
('5', 'Simples', 'Sem banheiro', '3 camas', 'livre'),
('6', 'Simples', 'Sem banheiro', '1 cama', 'livre'),
('7', 'Simples', 'Sem banheiro', '1 cama', 'livre'),
('8', 'Simples', 'Sem banheiro', '1 cama', 'livre'),
('9', 'Simples', 'Sem banheiro', '1 cama', 'livre'),
('12', 'Simples', 'Com banheiro', '2 camas', 'livre'),
('13', 'Simples', 'Com banheiro', '2 camas', 'livre'),
('14', 'Casal', 'Com banheiro', '1 cama casal', 'livre'),
('15', 'Casal', 'Com banheiro', '1 cama casal', 'livre'),
('16', 'Simples', 'Com banheiro', '3 camas', 'livre'),
('17', 'Simples', 'Com banheiro', '2 camas', 'livre'),
('18', 'Simples', 'Com banheiro', '2 camas', 'livre'),
('19', 'Simples', 'Com banheiro', '2 camas', 'livre'),
('20', 'Simples', 'Com banheiro', '2 camas', 'livre')
ON DUPLICATE KEY UPDATE
  tipo = VALUES(tipo),
  banheiro = VALUES(banheiro),
  camas = VALUES(camas),
  status = VALUES(status);

-- Pessoas base de demonstração
INSERT INTO pessoas (nome, documento, telefone, email) VALUES
('Mariana Souza', '12345678901', '33990001111', NULL),
('Carlos Henrique', '98765432100', '33998887766', NULL),
('Júlia Dias', '45612378900', '33998122068', NULL)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  telefone = VALUES(telefone),
  email = VALUES(email);
