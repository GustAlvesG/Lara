#!/bin/bash
#
# Deploy de produção do LARA.
#
# Para no primeiro erro (set -e) e diz em que linha parou: a versão anterior
# seguia em frente quando o `git pull` falhava e terminava anunciando sucesso
# com o site ainda na versão antiga.
#
# Uso:  ./deploy_prod.sh            (como administrator, com sudo, ou como root)

set -Eeuo pipefail

# --- Configurações ---
PROJECT_PATH="/var/www/html/Lara"
GIT_REPO="https://github.com/GustAlvesG/Lara.git"
GIT_BRANCH="main"
BACKUP_DIR="/home/administrator/lara_backup"
DB_NAME="lara"
DB_USER="administrator"
APACHE_USER="www-data"
SUPERVISOR_CONF="/etc/supervisor/conf.d/lara-queue.conf"

# Como root não precisa de sudo; como outro usuário, usa.
if [ "$(id -u)" -eq 0 ]; then SUDO=""; else SUDO="sudo"; fi
DEPLOY_USER="$(id -un)"

trap 'echo; echo "❌ Deploy INTERROMPIDO na linha $LINENO (comando: $BASH_COMMAND). O site pode estar na versão anterior — corrija o erro acima e rode de novo."' ERR

echo "🚀 Iniciando processo de deploy..."

# 1. Backup do banco -----------------------------------------------------------
mkdir -p "$BACKUP_DIR"
DATA_ATUAL=$(date +%d%m%Y_%H%M)
FILE_NAME="backuplara_$DATA_ATUAL.sql"

echo "📂 Gerando dump do banco em: $BACKUP_DIR/$FILE_NAME"
mysqldump -u "$DB_USER" -p "$DB_NAME" > "$BACKUP_DIR/$FILE_NAME"
echo "✅ Backup concluído."

# 2. Código --------------------------------------------------------------------
if [ -d "$PROJECT_PATH/.git" ]; then
    # O deploy anterior deixou a pasta com o usuário do Apache. Para o git, o
    # composer e o npm trabalharem, ela passa para quem está fazendo o deploy
    # (grupo do Apache, para o site seguir no ar); volta ao Apache no fim.
    echo "🔐 Assumindo a pasta do projeto para o deploy..."
    $SUDO chown -R "$DEPLOY_USER":"$APACHE_USER" "$PROJECT_PATH"
    git config --global --add safe.directory "$PROJECT_PATH"

    cd "$PROJECT_PATH"
    ANTES=$(git rev-parse --short HEAD)

    # O package-lock.json alterado no servidor (por um `npm install` antigo)
    # fazia o pull ser recusado. O arquivo certo é o do repositório.
    git checkout -- package-lock.json

    # Qualquer outra alteração local em arquivo versionado para o deploy aqui,
    # em vez de ser sobrescrita ou de travar o pull sem ninguém ver.
    if ! git diff --quiet || ! git diff --cached --quiet; then
        echo "❌ Há arquivos versionados alterados direto no servidor:"
        git status --short
        echo "   Confira-os. Para descartar: git checkout -- <arquivo>. Para guardar: git stash."
        exit 1
    fi

    echo "🔄 Atualizando repositório ($GIT_BRANCH)..."
    git fetch origin "$GIT_BRANCH"
    git pull --ff-only origin "$GIT_BRANCH"
else
    echo "📂 Clonando repositório..."
    git clone --branch "$GIT_BRANCH" "$GIT_REPO" "$PROJECT_PATH"
    cd "$PROJECT_PATH"
    ANTES="(instalação nova)"
fi

# Confere que o código é mesmo o do GitHub antes de seguir.
DEPOIS=$(git rev-parse --short HEAD)
if [ "$(git rev-parse HEAD)" != "$(git rev-parse FETCH_HEAD 2>/dev/null || git rev-parse HEAD)" ]; then
    echo "❌ O código local ($DEPOIS) não é o de origin/$GIT_BRANCH. Deploy cancelado."
    exit 1
fi
echo "✅ Código: $ANTES → $DEPOIS  ($(git log -1 --format=%s))"

# 3. Dependências PHP ----------------------------------------------------------
echo "📦 Rodando Composer..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-curl

# 4. Assets --------------------------------------------------------------------
# `npm ci` instala exatamente o package-lock.json e não o altera (o
# `npm install` alterava, e o deploy seguinte travava no pull).
if [ -f "package.json" ]; then
    echo "📦 Instalando dependências NPM..."
    npm ci
    echo "🔨 Construindo assets..."
    npm run build

    if [ ! -f "public/build/manifest.json" ]; then
        echo "❌ O build não gerou public/build/manifest.json."
        exit 1
    fi
fi

# 5. Laravel -------------------------------------------------------------------
echo "🗄️ Rodando migrations e refazendo caches..."
php artisan migrate --force

# Seed padrão: cria no banco as permissões declaradas no catálogo
# (App\Authorization\Permissions) que ainda não existem. Não dá permissão a
# nenhum setor nem altera o que já está configurado — a permissão nova nasce
# solta e é atribuída na tela de Setores.
echo "🔑 Sincronizando o catálogo de permissões..."
php artisan db:seed --class=PermissionCatalogSeeder --force
php artisan view:clear
php artisan config:cache
php artisan route:cache

# 6. Permissões ----------------------------------------------------------------
# No fim, depois do build e dos caches: tudo o que o deploy criou volta para o
# usuário do Apache.
echo "🔐 Devolvendo a pasta ao $APACHE_USER..."
$SUDO chown -R "$APACHE_USER":"$APACHE_USER" "$PROJECT_PATH"
$SUDO chmod -R 775 "$PROJECT_PATH/storage" "$PROJECT_PATH/bootstrap/cache"

# 7. Supervisor — instalar e configurar se necessário ----------------------------
echo "⚙️ Verificando Supervisor..."

if ! command -v supervisord &> /dev/null; then
    echo "📦 Instalando Supervisor..."
    $SUDO apt-get install -y supervisor
    $SUDO systemctl enable supervisor
    $SUDO systemctl start supervisor
fi

PHP_BIN=$(which php)

if [ ! -f "$SUPERVISOR_CONF" ]; then
    echo "📝 Criando configuração do Supervisor..."
    $SUDO tee "$SUPERVISOR_CONF" > /dev/null <<EOF
[program:lara-queue]
process_name=%(program_name)s_%(process_num)02d
command=$PHP_BIN $PROJECT_PATH/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=$PROJECT_PATH
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$APACHE_USER
numprocs=1
redirect_stderr=true
stdout_logfile=$PROJECT_PATH/storage/logs/queue.log
stopwaitsecs=3600
EOF
    $SUDO supervisorctl reread
    $SUDO supervisorctl update
    echo "✅ Supervisor configurado."
fi

# A cada deploy, reinicia o worker para carregar o novo código
echo "🔄 Reiniciando worker de filas..."
$SUDO supervisorctl restart lara-queue:* || echo "⚠️  Não consegui reiniciar o worker de filas; confira com: supervisorctl status"

# 8. Cron — scheduler do Laravel (avisos: lembretes e expirações) ----------------
CRON_JOB="* * * * * $PHP_BIN $PROJECT_PATH/artisan schedule:run >> /dev/null 2>&1"
CRON_MARKER="$PROJECT_PATH/artisan schedule:run"

echo "⏰ Verificando cron do scheduler Laravel..."
# `|| true`: crontab -l devolve erro quando o usuário ainda não tem crontab.
CRON_ATUAL=$($SUDO crontab -u "$APACHE_USER" -l 2>/dev/null || true)
if echo "$CRON_ATUAL" | grep -qF "$CRON_MARKER"; then
    echo "✅ Cron do scheduler já configurado."
else
    printf '%s\n%s\n' "$CRON_ATUAL" "$CRON_JOB" | sed '/^$/d' | $SUDO crontab -u "$APACHE_USER" -
    echo "✅ Cron do scheduler adicionado para o usuário $APACHE_USER."
fi

# 9. Servidor web --------------------------------------------------------------
echo "⚙️ Reiniciando Apache..."
$SUDO systemctl restart apache2

# Se o PHP roda em FPM, é ele que guarda o código em memória (OPcache).
for FPM in $(systemctl list-units --type=service --state=running --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do
    echo "⚙️ Reiniciando $FPM..."
    $SUDO systemctl restart "$FPM"
done

echo "✅ Deploy finalizado: versão $DEPOIS no ar ($DATA_ATUAL)."
