#!/bin/bash

# --- Configurações ---
PROJECT_PATH="/home/administrator/Lara"
GIT_REPO="https://github.com/GustAlvesG/Lara.git"
BACKUP_DIR="/home/administrator/lara_backup"
APACHE_USER="www-data"
SUPERVISOR_CONF="/etc/supervisor/conf.d/lara-queue.conf"

echo "🚀 Iniciando processo de deploy..."

# 1. Criar pasta de backup se não existir
mkdir -p $BACKUP_DIR

# 2. Dump do Banco de Dados (Dinâmico)
DATA_ATUAL=$(date +%d%m%Y)
FILE_NAME="backuplara_$DATA_ATUAL.sql"

echo "📂 Gerando dump do banco em: $BACKUP_DIR/$FILE_NAME"
mysqldump -u administrator -p lara > "$BACKUP_DIR/$FILE_NAME"

if [ $? -eq 0 ]; then
    echo "✅ Backup concluído."
else
    echo "❌ Erro no backup! Verifique a senha e as permissões."
    exit 1
fi

# 3. Atualizar Código
if [ -d "$PROJECT_PATH/.git" ]; then
    echo "🔄 Atualizando repositório..."
    cd $PROJECT_PATH && git pull origin main
else
    echo "📂 Clonando repositório..."
    git clone $GIT_REPO $PROJECT_PATH
    cd $PROJECT_PATH
fi

# 4. Instalar Dependências
echo "📦 Rodando Composer..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-curl --no-dev

# 5. Permissões e Pastas
echo "🔐 Ajustando permissões de storage e cache..."
sudo chown -R $APACHE_USER:$APACHE_USER $PROJECT_PATH
sudo chmod -R 775 storage bootstrap/cache

# 6. Rodando NPM e Build
if [ -f "package.json" ]; then
    echo "📦 Instalando dependências NPM..."
    npm install
    echo "🔨 Construindo assets..."
    npm run build
fi

# 7. Laravel Artisan
echo "🗄️ Rodando migrations e limpando caches..."
php artisan migrate

# Seed padrão: cria no banco as permissões do catálogo que ainda não existem.
# Não dá permissão a nenhum setor nem altera o que já está configurado.
echo "🔑 Sincronizando o catálogo de permissões..."
php artisan db:seed --class=PermissionCatalogSeeder --force
php artisan config:cache
php artisan route:cache

# Listas de certificados revogados (gov.br e ICP-Brasil) da conferência do PDF
# assinado: baixa já as que faltam, para a primeira conferência não esperar o
# download (~3 MB). Depois, o scheduler as renova de hora em hora. Sem rede, o
# comando só avisa — não para o deploy.
php artisan signature:crl || echo "⚠️ Listas de revogação não renovadas agora; o scheduler tenta de hora em hora."

# 8. Supervisor — instalar e configurar se necessário
echo "⚙️ Verificando Supervisor..."

if ! command -v supervisord &> /dev/null; then
    echo "📦 Instalando Supervisor..."
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor
fi

PHP_BIN=$(which php)

if [ ! -f "$SUPERVISOR_CONF" ]; then
    echo "📝 Criando configuração do Supervisor..."
    sudo tee $SUPERVISOR_CONF > /dev/null <<EOF
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
    sudo supervisorctl reread
    sudo supervisorctl update
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

# 9. Cron — scheduler do Laravel (avisos: lembretes e expirações)
CRON_JOB="* * * * * $PHP_BIN $PROJECT_PATH/artisan schedule:run >> /dev/null 2>&1"
CRON_MARKER="$PROJECT_PATH/artisan schedule:run"

echo "⏰ Verificando cron do scheduler Laravel..."
if sudo crontab -u $APACHE_USER -l 2>/dev/null | grep -qF "$CRON_MARKER"; then
    echo "✅ Cron do scheduler já configurado."
else
    (sudo crontab -u $APACHE_USER -l 2>/dev/null; echo "$CRON_JOB") | sudo crontab -u $APACHE_USER -
    echo "✅ Cron do scheduler adicionado para o usuário $APACHE_USER."
fi

# 10. Finalização
echo "⚙️ Reiniciando Apache..."
sudo systemctl restart apache2

echo "✅ Deploy finalizado com sucesso em $DATA_ATUAL!"