# 2. Instalação e Configuração

## Requisitos

- **PHP** >= 8.2 (com extensões `pdo_mysql` e, para a base externa, `pdo_sqlsrv`/`sqlsrv`)
- **Composer** 2.x
- **Node.js** + npm (build do front-end com Vite)
- **MySQL** 8.x (banco principal)
- **SQL Server** (opcional — base externa MultiClubes para sócios/acessos)

## Passo a passo

```bash
# 1. Instalar dependências PHP e JS
composer install
npm install

# 2. Criar o arquivo de ambiente e gerar a chave
cp .env.example .env
php artisan key:generate

# 3. Configurar o .env (ver seção abaixo)

# 4. Rodar as migrações e os seeders
php artisan migrate
php artisan db:seed

# 5. Compilar assets
npm run dev      # desenvolvimento
npm run build    # produção

# 6. Subir o servidor de desenvolvimento
php artisan serve
```

## Variáveis de ambiente (`.env`)

### Aplicação
```env
APP_NAME=Laravel
APP_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
APP_URL=http://localhost
```

### Banco principal (MySQL)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

### Banco secundário — SQL Server (MultiClubes)
Usado pelos models `Access`, `Visitor` e por consultas diretas de `Member`.
```env
MC_DB_CONNECTION=sqlsrv
MC_DB_HOST=
MC_DB_PORT=1433
MC_DB_DATABASE=
MC_DB_USERNAME=
MC_DB_PASSWORD=
```

### Pagamentos (Itaú / Rede)
```env
ITAU_CLIENT_ID=
ITAU_CLIENT_SECRET=
REDE_PV=
REDE_TOKEN=
REDE_BASE_URL=
```

### Telegram
```env
TELEGRAM_BOT_TOKEN=
```

### WhatsApp / Meta
```env
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_VERIFY_TOKEN=
```

### Token da API
Token estático exigido pelo middleware `api_token` (header `Authorization: Bearer <token>`).
```env
API_TOKEN=
```

### E-mail e filas
```env
MAIL_MAILER=log        # smtp em produção
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

## Filas (processamento assíncrono)

O webhook do WhatsApp é processado por um **Job** (`ProcessWhatsAppWebhook`) na fila
`database`. Para processá-la:

```bash
php artisan queue:work
```

## Deploy

O repositório inclui `deploy_hml.sh`, um script de deploy para o ambiente de homologação.
Revise-o antes de executar; em produção, garanta:

```bash
php artisan config:cache
php artisan route:cache
php artisan migrate --force
npm run build
```

## Testes

```bash
php artisan test     # ou ./vendor/bin/phpunit
```
Configuração em `phpunit.xml` (usa banco em memória/sqlite por padrão para testes).
