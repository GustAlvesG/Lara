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

# Chaves que o .env.example tem e o .env do servidor não tem, cada uma com o
# comentário que a antecede no .env.example. É só aviso: chave nova quase
# sempre tem valor padrão no código, e quem decide se ela precisa de valor no
# servidor é quem está fazendo o deploy — o comentário diz para que serve.
#
# "Comentário anterior" é o bloco de linhas com # logo acima da chave. Várias
# chaves seguidas sob o mesmo comentário o compartilham, e ele sai uma vez só.
# Chave comentada no .env (# CHAVE=...) conta como ausente.
chaves_faltando_no_env() {
    [ -f .env.example ] || return 0

    local env_file=".env"
    [ -f "$env_file" ] || env_file="/dev/null"

    awk '
        function chave(linha) {
            sub(/^[ \t]*(export[ \t]+)?/, "", linha)
            return substr(linha, 1, index(linha, "=") - 1)
        }

        { sub(/\r$/, "") }

        # Primeiro arquivo: as chaves que o .env tem. (Pelo nome do arquivo, e
        # não por FNR == NR: com o .env vazio ou ausente, essa conta trataria o
        # .env.example como se fosse o .env.)
        FILENAME == ARGV[1] {
            if ($0 ~ /^[ \t]*(export[ \t]+)?[A-Za-z_][A-Za-z0-9_]*=/) tem[chave($0)] = 1
            next
        }

        # Segundo arquivo: o .env.example.
        /^[ \t]*$/ { comentario = ""; impresso = 0; depois_de_chave = 0; next }

        /^[ \t]*#/ {
            if (depois_de_chave) { comentario = ""; impresso = 0; depois_de_chave = 0 }
            comentario = comentario $0 "\n"
            next
        }

        /^[ \t]*(export[ \t]+)?[A-Za-z_][A-Za-z0-9_]*=/ {
            depois_de_chave = 1
            if (!(chave($0) in tem)) {
                if (comentario != "" && !impresso) { printf "\n%s", comentario; impresso = 1 }
                else if (comentario == "") printf "\n"
                print $0
            }
        }
    ' "$env_file" .env.example
}

# Guarda a lista para repetir no fim do deploy, onde ela não se perde na rolagem.
CHAVES_FALTANDO=""

mostra_chaves_faltando() {
    if [ -z "$CHAVES_FALTANDO" ]; then
        echo "✅ .env: tem todas as chaves do .env.example."
        return 0
    fi

    echo "⚠️  Chaves do .env.example que NÃO estão no .env deste servidor ($(printf '%s\n' "$CHAVES_FALTANDO" | grep -c '^[^#]*=')):"
    printf '%s\n' "$CHAVES_FALTANDO" | sed 's/^/    /'
    echo
    echo "    Inclua no .env as que este servidor precisa e rode: php artisan config:cache"
}

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

    # O que SEMPRE aparece diferente no servidor sem ninguém ter editado nada:
    #
    # 1. Permissão de arquivo. O passo 6 dá chmod 775 em storage/ e
    #    bootstrap/cache/, e o git via isso como alteração nos .gitignore de lá.
    #    Num servidor, o modo do arquivo não é mudança de código.
    git config core.fileMode false

    # 2. Arquivos que o próprio deploy regera: o package-lock.json (um
    #    `npm install` antigo o alterava) e o cache de pacotes do Laravel
    #    (packages.php e services.php, refeitos pelo composer no passo 3).
    #    Voltam ao que o repositório tem; o deploy os refaz logo adiante.
    #    O `ls-files` é porque o cache de pacotes deixa de ser versionado — aí
    #    não há o que restaurar.
    for GERADO in package-lock.json bootstrap/cache/packages.php bootstrap/cache/services.php; do
        if git ls-files --error-unmatch "$GERADO" > /dev/null 2>&1; then
            git checkout -- "$GERADO"
        fi
    done

    # Qualquer OUTRA alteração local em arquivo versionado para o deploy aqui,
    # em vez de ser sobrescrita ou de travar o pull sem ninguém ver. Arquivo
    # novo, não versionado (imagem enviada pelo sistema em public/images, por
    # exemplo), não entra na conta nem na lista.
    if ! git diff --quiet || ! git diff --cached --quiet; then
        echo "❌ Há arquivos versionados alterados direto no servidor:"
        git status --short --untracked-files=no
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

# Com o .env.example já atualizado pelo pull: o que falta no .env daqui.
CHAVES_FALTANDO="$(chaves_faltando_no_env)"
mostra_chaves_faltando

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

# Listas de certificados revogados (gov.br e ICP-Brasil) da conferência do PDF
# assinado: baixa já as que faltam, para a primeira conferência não esperar o
# download (~3 MB). Depois, o scheduler as renova de hora em hora. Sem rede, o
# comando só avisa — não para o deploy.
php artisan signature:crl || echo "⚠️ Listas de revogação não renovadas agora; o scheduler tenta de hora em hora."

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

# Um worker só, sempre. Dois consumindo a mesma fila já deram problema em
# produção (02/10/2026): um programa antigo do Supervisor ("laravel-worker")
# seguiu no ar ao lado do lara-queue, o deploy só reiniciava este, e o outro
# ficou respondendo com o código e o .env de antes. Além disso, dois workers
# processam fora de ordem duas mensagens seguidas da mesma conversa.
#
# A cada deploy:
#   1. outro programa do Supervisor que rode o queue:work DESTA instalação é
#      parado e o arquivo dele é renomeado (.desativado-<data>) — guardado, não
#      apagado. Worker de fila dedicada (com --queue=) não é duplicata: fica;
#   2. o lara-queue volta a numprocs=1 se alguém aumentou;
#   3. o lara-queue é reiniciado, para carregar o código e a configuração novos;
#   4. queue:work solto (aberto à mão, nohup, screen) recebe TERM — o Laravel
#      termina o job em andamento antes de sair.
garante_um_worker() {
    local SUDO="${SUDO-sudo}"
    local marcador="$PROJECT_PATH/artisan queue:work"
    local conf comando pid mudou=0

    # As conferências abaixo não usam pipe para um `grep -q`: com `pipefail`,
    # o grep que sai cedo pode derrubar o comando anterior (SIGPIPE), e a
    # conferência responderia "não" para um worker duplicado.
    for conf in /etc/supervisor/conf.d/*.conf; do
        [ -f "$conf" ] || continue
        [ "$conf" = "$SUPERVISOR_CONF" ] && continue

        comando=$(grep -E '^[[:space:]]*command[[:space:]]*=' "$conf" || true)
        case "$comando" in
            *"$marcador"*) ;;
            *) continue ;;
        esac

        case "$comando" in
            *--queue*)
                echo "ℹ️  $conf roda um worker de fila dedicada (--queue=): mantido."
                continue
                ;;
        esac

        echo "⚠️  Worker duplicado no Supervisor: $conf — desativando (o arquivo fica guardado ao lado)."
        $SUDO mv "$conf" "$conf.desativado-$(date +%Y%m%d%H%M)"
        mudou=1
    done

    if grep -Eq '^[[:space:]]*numprocs[[:space:]]*=' "$SUPERVISOR_CONF" \
        && ! grep -Eq '^[[:space:]]*numprocs[[:space:]]*=[[:space:]]*1[[:space:]]*$' "$SUPERVISOR_CONF"; then
        echo "⚠️  $SUPERVISOR_CONF tinha numprocs diferente de 1 — voltando para 1."
        $SUDO sed -i -E 's/^[[:space:]]*numprocs[[:space:]]*=.*/numprocs=1/' "$SUPERVISOR_CONF"
        mudou=1
    fi

    # O update para os programas cujo arquivo saiu e aplica o numprocs.
    if [ "$mudou" -eq 1 ]; then
        $SUDO supervisorctl reread || true
        $SUDO supervisorctl update || true
    fi

    echo "🔄 Reiniciando worker de filas..."
    $SUDO supervisorctl restart 'lara-queue:*' || echo "⚠️  Não consegui reiniciar o worker de filas; confira com: supervisorctl status"

    # Os PIDs que o Supervisor reconhece como lara-queue, um por linha; o
    # resto é sobra.
    local oficiais
    oficiais=$($SUDO supervisorctl status 'lara-queue:*' 2>/dev/null | sed -n 's/.*pid \([0-9][0-9]*\),.*/\1/p' || true)

    e_oficial() {
        local p
        for p in $oficiais; do
            [ "$p" = "$1" ] && return 0
        done
        return 1
    }

    # Worker de fila dedicada (mantido acima) não entra na conta.
    fila_dedicada() {
        local linha
        linha=$(tr '\0' ' ' 2>/dev/null < "/proc/$1/cmdline" || true)
        case "$linha" in
            *--queue*) return 0 ;;
        esac
        return 1
    }

    for pid in $(pgrep -f "$marcador" || true); do
        if e_oficial "$pid" || fila_dedicada "$pid"; then
            continue
        fi

        echo "⚠️  queue:work fora do Supervisor (pid $pid) — encerrando."
        $SUDO kill -TERM "$pid" 2>/dev/null || true
    done

    sleep 3

    local total=0 sobras=""
    for pid in $(pgrep -f "$marcador" || true); do
        if fila_dedicada "$pid"; then
            continue
        fi

        total=$((total + 1))
        if ! e_oficial "$pid"; then
            sobras="$sobras $pid"
        fi
    done

    if [ -z "$oficiais" ]; then
        echo "❌ O worker lara-queue NÃO está rodando: nada da fila será processado (bot do WhatsApp, avisos, importações). Confira: supervisorctl status"
    elif [ "$total" -eq 1 ]; then
        echo "✅ Fila: 1 worker no ar (pid $oficiais)."
    else
        echo "⚠️  Fila: $total workers no ar. Além do lara-queue (pid" $oficiais"), sobrou:$sobras — está terminando o job em andamento e sai sozinho. Se continuar em 'ps aux | grep queue:work', encerre com kill."
    fi
}

garante_um_worker

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

# De novo, por último: é o que fica na tela quando o deploy termina.
echo
mostra_chaves_faltando
