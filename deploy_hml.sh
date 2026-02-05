#!/bin/bash

# --- Configurações ---
PROJECT_PATH="/home/administrator/Lara"
GIT_REPO="https://github.com/GustAlvesG/Lara.git" # Atualize com seu link
BACKUP_DIR="/home/administrator/lara_backup"
APACHE_USER="www-data"

echo "🚀 Iniciando processo de deploy..."

# 1. Criar pasta de backup se não existir
mkdir -p $BACKUP_DIR

# 2. Dump do Banco de Dados (Dinâmico)
DATA_ATUAL=$(date +%d%m%Y)
FILE_NAME="backuplara_$DATA_ATUAL.sql"

echo "📂 Gerando dump do banco em: $BACKUP_DIR/$FILE_NAME"
# Nota: O sistema pedirá a senha do banco se não houver um .my.cnf configurado
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

# 4. Instalar Dependências (Resolvendo erro de Superuser)
echo "📦 Rodando Composer..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-curl

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
php artisan config:cache
php artisan route:cache

# 8. Finalização
echo "⚙️ Reiniciando Apache..."
sudo systemctl restart apache2

echo "✅ Deploy finalizado com sucesso em $DATA_ATUAL!"