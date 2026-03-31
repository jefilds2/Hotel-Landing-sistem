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

## IMAGENS:

<img width="1904" height="998" alt="Página inicial do site institucional do hotel (1)" src="https://github.com/user-attachments/assets/186d2c70-bc32-42ae-82ec-43edb5204926" />
<img width="1905" height="948" alt="Página inicial do site institucional do hotel (2)" src="https://github.com/user-attachments/assets/eb9c3047-77e2-49b2-b579-f2a3275ef83c" />
<img width="1902" height="942" alt="Página inicial do site institucional do hotel (3)" src="https://github.com/user-attachments/assets/6da958ae-1eb7-4b3b-a540-9646d1c6507c" />
<img width="1900" height="955" alt="Integração com Google Maps para rota até o hotel" src="https://github.com/user-attachments/assets/e5d507a3-998c-4129-b806-2bbe3a021e1d" />
<img width="1231" height="947" alt="Painel de gerenciamento dos quartos" src="https://github.com/user-attachments/assets/7870b9bf-c28d-45b4-89d1-03cace4da18c" />
<img width="1201" height="946" alt="Gestão da estadia e status específico de cada quarto" src="https://github.com/user-attachments/assets/f9109d95-d75a-459a-bb1a-7de0d3a4fe58" />
<img width="1231" height="951" alt="Vinculação de cliente e acompanhantes ao quarto" src="https://github.com/user-attachments/assets/b1af4c3f-8e91-46f1-b030-e1aa14e274d5" />
<img width="1217" height="947" alt="Página de histórico de estadias" src="https://github.com/user-attachments/assets/2fc346b1-c99b-4def-8e30-5aa630cd71ea" />
<img width="1187" height="940" alt="Página de cadastro de hóspedes" src="https://github.com/user-attachments/assets/b0ecfddf-3949-42b5-a5b2-ca063b4d174b" />
<img width="1242" height="695" alt="Página de gestão de usuários do sistema" src="https://github.com/user-attachments/assets/714c8cae-2a36-4700-9331-2d8587424692" />
<img width="1338" height="880" alt="Modelo de impressão das informações do quarto" src="https://github.com/user-attachments/assets/385d51d3-5c1d-4d36-aab6-1d54a9fb4b0c" />

