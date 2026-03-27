# Hotel Bela Vista

Sistema web para portfólio que reúne uma landing page institucional e um painel administrativo para operação hoteleira. O projeto foi desenvolvido para o Hotel Bela Vista, com foco em apresentação comercial, gestão de quartos, controle de estadias e cadastro de hóspedes em um único repositório.

## Visão geral

O repositório contém duas frentes no mesmo projeto:

- Frontend público com landing page responsiva, foco em conversão via WhatsApp e otimização de SEO.
- Backend em PHP com painel administrativo para gestão de quartos, hóspedes, usuários e histórico operacional.

## Principais funcionalidades

### Site público

- Landing page responsiva para divulgação do hotel
- CTA para reserva via WhatsApp e ligação direta
- Seções de quartos, café da manhã, ambiente e localização
- SEO técnico com meta tags, Open Graph, Twitter Cards, `schema.org`, `robots.txt` e `sitemap.xml`
- Carrosséis e animações com JavaScript vanilla

### Painel administrativo

- Login com perfis `master` e `admin`
- Dashboard com visualização de quartos e status operacionais
- Controle de reservas, check-in, check-out, limpeza e manutenção
- Cadastro e edição de hóspedes
- Inclusão de acompanhantes por estadia
- Histórico de hospedagens com filtros
- Impressão/exportação de relatórios e ficha de hóspede
- Auditoria básica de ações do sistema

## Stack identificada no código

### Frontend

- HTML5
- CSS3
- JavaScript vanilla
- Google Fonts (`Cormorant Garamond`, `Manrope`, `Sora`)
- SEO estruturado com JSON-LD (`schema.org/Hotel`)

### Backend

- PHP 8+ com `declare(strict_types=1)`
- Sessões nativas do PHP
- PDO para acesso ao banco
- Prepared statements
- `password_hash` e `password_verify`
- Proteção CSRF com token em sessão

### Banco de dados e infraestrutura

- MySQL / MariaDB
- SQL puro para estrutura e seeds
- Docker Compose para ambiente local do banco

## Bibliotecas e tecnologias realmente encontradas

### Frontend

- `Intl.NumberFormat`
- `IntersectionObserver`
- `matchMedia`
- `Google Fonts`

### Backend

- `PDO`
- `DateTimeImmutable`
- `random_bytes`
- `hash_equals`
- `htmlspecialchars`

### Integrações e recursos identificados

- Banco de dados relacional MySQL/MariaDB
- Autenticação baseada em sessão
- Controle de acesso por nível de usuário (`master` e `admin`)
- Exportação de dados via CSV com `fopen('php://output', 'w')`
- Auditoria de ações em tabela própria

### Não foram encontrados no código

- Framework PHP
- ORM ou ODM
- Gateway de pagamento
- Upload de arquivos
- Composer e dependências de terceiros instaladas via `vendor/`
- Node.js, bundler ou gerenciador de pacotes frontend

## Estrutura do projeto

```text
.
|-- admin/
|   `-- includes/              # arquivos de compatibilidade/redirecionamento
|-- banco/
|   |-- estrutura.sql          # schema do banco
|   |-- seed.sql               # dados base
|   `-- seed_dev_usuarios.sql  # usuarios de desenvolvimento
|-- config/
|   `-- config.php             # leitura de variaveis do .env
|-- public_html/
|   |-- index.html             # landing page publica
|   |-- assets/
|   |   |-- css/site.css
|   |   |-- js/site.js
|   |   `-- img/
|   |-- admin/                 # painel administrativo
|   `-- portal/                # painel administrativo/rota alternativa
|-- .env.example
|-- docker-compose.yml
`-- README.md
```

## Banco de dados

O schema encontrado em `banco/estrutura.sql` cria as tabelas:

- `usuarios`
- `quartos`
- `pessoas`
- `estadias`
- `estadia_acompanhantes`
- `login_tentativas`
- `auditoria_logs`

## Como rodar localmente

### 1. Configurar variáveis de ambiente

```powershell
Copy-Item .env.example .env
```

### 2. Subir o banco local

```bash
docker-compose up -d
```

### 3. Importar estrutura e dados

PowerShell:

```powershell
Get-Content .\banco\estrutura.sql -Raw | docker exec -i hotel-bela-vista-db mariadb -u root -proot_hotel_123
Get-Content .\banco\seed.sql -Raw | docker exec -i hotel-bela-vista-db mariadb -u root -proot_hotel_123
Get-Content .\banco\seed_dev_usuarios.sql -Raw | docker exec -i hotel-bela-vista-db mariadb -u root -proot_hotel_123
```

### 4. Subir o servidor PHP

```bash
php -S localhost:8080 -t public_html
```

### 5. Acessar a aplicação

- Site público: `http://localhost:8080`
- Painel: `http://localhost:8080/portal/login.php`

## Usuários de desenvolvimento

- `master@hotelbelavista.local` / `Master@123`
- `admin@hotelbelavista.local` / `Admin@123`

Esses usuários vêm de `banco/seed_dev_usuarios.sql` e são destinados apenas ao ambiente local.

## Segurança implementada

- Hash de senha com `password_hash`
- Validação de senha com `password_verify`
- Proteção CSRF em formulários
- Escape de saída com `htmlspecialchars`
- Sessão com `httponly` e `samesite=Lax`
- Bloqueio temporário após tentativas de login
- Logging de auditoria no banco
- Tratamento de falhas de conexão com `try/catch`

## Observações

- O projeto usa estrutura PHP tradicional sem framework.
- O diretório `public_html/admin/` replica a área administrativa disponível também em `public_html/portal/`.
- O arquivo `.env` não deve ser versionado.
