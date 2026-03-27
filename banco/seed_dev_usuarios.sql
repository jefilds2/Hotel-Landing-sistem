USE hotel_bela_vista;

-- Seed exclusivo de desenvolvimento.
-- Nao utilizar em producao.
-- Este arquivo cria/atualiza usuarios padrao para facilitar testes locais.

INSERT INTO usuarios (nome, email, usuario, senha_hash, nivel, ativo) VALUES
('Conta Master', 'master@hotelbelavista.local', 'master', '$2y$10$HkklU3tG/nhJ.hi13TFfuumNkNkqeSw.hffHV3pxofdG5EM6wmldm', 'master', 1),
('Administrador Geral', 'admin@hotelbelavista.local', 'admin', '$2y$10$fBGUt8bz.QqUiF59f2c5WuGa3Z5kiK/Ic/muafsPA9W72zdL4Nox6', 'admin', 1)
ON DUPLICATE KEY UPDATE
  nome = VALUES(nome),
  usuario = VALUES(usuario),
  senha_hash = VALUES(senha_hash),
  nivel = VALUES(nivel),
  ativo = VALUES(ativo);

