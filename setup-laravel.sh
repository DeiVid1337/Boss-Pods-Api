#!/bin/bash

# Script para criar projeto Laravel usando Docker
# Uso: ./setup-laravel.sh

echo "🚀 Iniciando setup do projeto Laravel..."
echo ""

# Verificar se está na pasta correta
CURRENT_DIR=$(pwd)
echo "📁 Diretório atual: $CURRENT_DIR"
echo ""

# Verificar se já existe composer.json
if [ -f "composer.json" ]; then
    echo "✅ composer.json já existe!"
    echo "   O projeto Laravel parece já estar criado."
    echo ""
    read -p "   Deseja recriar? Isso irá sobrescrever arquivos existentes do Laravel (s/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        echo "❌ Operação cancelada. Projeto já existe."
        exit 0
    fi
    echo "🔄 Recriando projeto Laravel..."
fi

echo "📦 Criando projeto Laravel em diretório temporário..."
echo "   (Isso pode demorar alguns minutos na primeira vez)"
echo ""

# Criar diretório temporário para o Laravel
TEMP_DIR="laravel_temp_$(date +%s)"
CURRENT_DIR=$(pwd)

# Criar Laravel em diretório temporário dentro do diretório atual
docker run --rm \
    -v "$CURRENT_DIR:/workspace" \
    -w /workspace \
    composer \
    create-project laravel/laravel "$TEMP_DIR" --prefer-dist --no-interaction

# Verificar se foi criado com sucesso
if [ ! -d "$TEMP_DIR" ]; then
    echo "❌ Erro: Falha ao criar projeto Laravel"
    exit 1
fi

echo ""
echo "📋 Movendo arquivos do Laravel para o diretório atual..."
echo ""

# Lista de arquivos e diretórios do Laravel para mover
LARAVEL_ITEMS=(
    "app"
    "bootstrap"
    "config"
    "database"
    "public"
    "resources"
    "routes"
    "storage"
    "tests"
    "vendor"
    ".env.example"
    "artisan"
    "composer.json"
    "composer.lock"
    "package.json"
    "phpunit.xml"
    "vite.config.js"
    ".gitignore"
    ".gitattributes"
)

# Mover cada item, ignorando se já existir (para preservar Docs/, Prompts/, etc.)
for item in "${LARAVEL_ITEMS[@]}"; do
    if [ -e "$TEMP_DIR/$item" ]; then
        if [ -e "$item" ]; then
            echo "   ⚠️  $item já existe, mantendo o existente"
        else
            echo "   ✅ Movendo $item"
            mv "$TEMP_DIR/$item" .
        fi
    fi
done

# Remover diretório temporário
echo ""
echo "🧹 Limpando diretório temporário..."
rm -rf "$TEMP_DIR"

echo ""
echo "✅ Projeto Laravel criado com sucesso!"
echo ""
echo "📝 Próximos passos:"
echo "   1. Execute: docker compose up -d --build"
echo "   2. Execute: docker compose exec app composer install"
echo "   3. Execute: docker compose exec app cp .env.example .env"
echo "   4. Edite o arquivo .env e configure o banco de dados:"
echo "      DB_CONNECTION=pgsql"
echo "      DB_HOST=postgres"
echo "      DB_PORT=5432"
echo "      DB_DATABASE=boss_pods"
echo "      DB_USERNAME=postgres"
echo "      DB_PASSWORD=postgres"
echo "   5. Execute: docker compose exec app php artisan key:generate"
echo "   6. Execute: docker compose exec app chmod -R 775 storage bootstrap/cache"
echo "   7. Execute: docker compose exec app php artisan migrate"
echo ""
