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

### Replay (vídeos das quadras)
```env
# Site de locação de espaços — destino do link do e-mail do sócio.
REPLAY_PORTAL_URL=https://locacao.clubedosfuncionarios.com.br
# Só para compor o overlay ANIMADO (GIF -> WebM com canal alpha).
REPLAY_FFMPEG_PATH=ffmpeg
```

Duas exigências de **servidor**, não de aplicação:

1. **`upload_max_filesize` e `post_max_size` em 256M** no `php.ini`, com reinício do Apache
   (e `LimitRequestBody` no Apache, se estiver configurado). O servidor está hoje em 30M, o que
   recusa um clipe de 60s a 1080p **antes** de a requisição chegar ao Laravel — o sintoma é um
   422 com corpo vazio, que não aponta para a causa.
2. **ffmpeg instalado** — opcional. Sem ele o módulo funciona inteiro: logomarca em GIF animado
   sai parada (primeiro quadro) e a API entrega só o PNG. A tela de layouts avisa.

Token da integração com o sistema de captura: `php artisan replay:token captura-producao`.

## Filas (processamento assíncrono)

O webhook do WhatsApp é processado por um **Job** (`ProcessWhatsAppWebhook`) na fila
`database`. Para processá-la:

```bash
php artisan queue:work
```

## Deploy

O repositório inclui `deploy_hml.sh` (homologação) e `deploy_prod.sh` (produção). O de produção
faz, nesta ordem: backup do banco, `git pull`, `composer install`, `npm ci` + build, migrations,
o seed padrão de permissões (`PermissionCatalogSeeder`), caches, permissões de pasta, worker de
filas, cron e reinício do servidor web. Ele para no primeiro erro e diz em que linha parou.

### Chaves do `.env.example` que faltam no `.env`

**Um worker de fila só.** Os dois scripts garantem que a fila tenha exatamente um `queue:work`,
o programa `lara-queue` do Supervisor. A cada deploy: outro programa do Supervisor que rode o
`queue:work` desta instalação é parado e o arquivo dele em `/etc/supervisor/conf.d/` é renomeado
para `.desativado-<data>` (guardado, não apagado); o `numprocs` do `lara-queue` volta a 1; o
`lara-queue` é reiniciado; e `queue:work` aberto à mão recebe `TERM` (termina o job em andamento
e sai). No fim o script diz quantos workers ficaram no ar. Dois workers já fizeram o bot do
WhatsApp responder com configuração antiga (o deploy reiniciava um só) e podem processar fora de
ordem duas mensagens da mesma conversa. Um worker de fila dedicada (comando com `--queue=`) não
é tratado como duplicata e fica.

A cada deploy, logo depois do `git pull` e de novo no fim, o `deploy_prod.sh` lista as chaves
que existem no `.env.example` e **não** existem no `.env` do servidor, cada uma com o comentário
que a antecede no `.env.example` (o bloco de linhas com `#` logo acima; chaves seguidas sob o
mesmo comentário o compartilham). É só aviso — o deploy não para por isso: quem decide se a chave
precisa de valor naquele servidor é quem está fazendo o deploy, e o comentário diz para que ela
serve. Chave comentada no `.env` (`# CHAVE=...`) conta como ausente.

Por isso, **toda chave nova entra no `.env.example` com um comentário acima** dizendo para que
serve: é esse texto que aparece no deploy.

### "Há arquivos versionados alterados direto no servidor"

Antes do `git pull`, o `deploy_prod.sh` confere se alguém editou arquivo versionado direto no
servidor — e para, em vez de sobrescrever. Três coisas aparecem diferentes **sempre**, sem ninguém
ter editado nada, e o script as trata sozinho:

| O que aparece | Por quê | O que o script faz |
|---|---|---|
| `.gitignore` de `storage/` e `bootstrap/cache/` | o próprio deploy dá `chmod 775` nessas pastas, e o git vê a troca de permissão como alteração | `git config core.fileMode false`: modo de arquivo não conta como mudança |
| `bootstrap/cache/packages.php` e `services.php` | cache de pacotes do Laravel, refeito pelo `composer install` | restaura antes do pull; os arquivos **não são mais versionados** |
| `package-lock.json` | um `npm install` antigo o alterava | restaura antes do pull (o deploy usa `npm ci`, que não o altera) |

Arquivo **novo**, não versionado (`??` no `git status`) — imagem enviada pelo sistema em
`public/images`, por exemplo — não bloqueia o deploy nem entra na lista do aviso.

Se o aviso aparecer mesmo assim, é alteração de verdade: confira o arquivo, e use
`git checkout -- <arquivo>` para descartar ou `git stash` para guardar.

> Arquivo gerado em tempo de execução não deve ser versionado. Se um novo aparecer nessa lista a
> cada deploy, o conserto é tirá-lo do git (`git rm --cached`), e não acrescentar exceção.

## Testes

```bash
php artisan test     # ou ./vendor/bin/phpunit
```
Configuração em `phpunit.xml` (usa banco em memória/sqlite por padrão para testes).
