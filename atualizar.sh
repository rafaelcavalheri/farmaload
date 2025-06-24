#!/bin/bash

# Obter o diretório base do script
BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

# Configurações
GITHUB_REPO="rafaelcavalheri/farmaload"
DEST="$BASE_DIR/farmacia"
BACKUP_DIR="$BASE_DIR/backup_farmacia_$(date +%Y%m%d_%H%M%S)"
TEMP_DIR="$BASE_DIR/temp_farmacia_$(date +%Y%m%d_%H%M%S)"
USER="www-data"
GROUP="www-data"

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para log colorido
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar dependências
check_dependencies() {
    local deps=("curl" "jq" "unzip" "docker-compose")
    local missing=()
    
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            missing+=("$dep")
        fi
    done
    
    if [ ${#missing[@]} -ne 0 ]; then
        log_error "Dependências faltando: ${missing[*]}"
        log_info "Instale as dependências necessárias antes de continuar."
        exit 1
    fi
}

# Obter a última versão do GitHub
get_latest_version() {
    log_info "Buscando última versão no GitHub..."
    
    local api_url="https://api.github.com/repos/$GITHUB_REPO/releases/latest"
    local response
    
    response=$(curl -s -H "User-Agent: Farmaload-Updater" "$api_url")
    
    if [ $? -ne 0 ]; then
        log_error "Erro ao conectar com GitHub API"
        return 1
    fi
    
    local version=$(echo "$response" | jq -r '.tag_name')
    local download_url=$(echo "$response" | jq -r '.assets[0].browser_download_url')
    
    if [ "$version" = "null" ] || [ "$download_url" = "null" ]; then
        log_error "Não foi possível obter informações da última versão"
        return 1
    fi
    
    echo "$version|$download_url"
}

# Baixar e descompactar a versão
download_and_extract() {
    local version_info="$1"
    local version=$(echo "$version_info" | cut -d'|' -f1)
    local download_url=$(echo "$version_info" | cut -d'|' -f2)
    
    log_info "Baixando versão $version..."
    
    # Criar diretório temporário
    mkdir -p "$TEMP_DIR"
    
    # Baixar arquivo
    local zip_file="$TEMP_DIR/farmaload_$version.zip"
    if ! curl -L -H "User-Agent: Farmaload-Updater" -o "$zip_file" "$download_url"; then
        log_error "Erro ao baixar arquivo"
        return 1
    fi
    
    log_info "Descompactando arquivo..."
    if ! unzip -q "$zip_file" -d "$TEMP_DIR"; then
        log_error "Erro ao descompactar arquivo"
        return 1
    fi
    
    # Procurar pela pasta farmacia no conteúdo descompactado
    local farmacia_dir
    if [ -d "$TEMP_DIR/farmacia" ]; then
        farmacia_dir="$TEMP_DIR/farmacia"
    elif [ -d "$TEMP_DIR/farmaload/farmacia" ]; then
        farmacia_dir="$TEMP_DIR/farmaload/farmacia"
    else
        # Procurar recursivamente
        farmacia_dir=$(find "$TEMP_DIR" -type d -name "farmacia" | head -1)
    fi
    
    if [ -z "$farmacia_dir" ] || [ ! -d "$farmacia_dir" ]; then
        log_error "Pasta 'farmacia' não encontrada no arquivo baixado"
        return 1
    fi
    
    echo "$farmacia_dir"
}

# Backup da versão atual
backup_atual() {
    log_info "Fazendo backup da versão atual..."
    
    if [ -d "$DEST" ]; then
        mkdir -p "$(dirname "$BACKUP_DIR")"
        if cp -a "$DEST" "$BACKUP_DIR"; then
            log_success "Backup criado em: $BACKUP_DIR"
        else
            log_error "Erro ao criar backup"
            return 1
        fi
    else
        log_warning "Diretório de destino não existe, pulando backup"
    fi
}

# Restaurar backup
restaurar_backup() {
    if [ -d "$BACKUP_DIR" ]; then
        log_info "Restaurando backup..."
        rm -rf "$DEST"
        if cp -a "$BACKUP_DIR" "$DEST"; then
            chown -R $USER:$GROUP "$DEST"
            find "$DEST" -type d -exec chmod 755 {} \;
            find "$DEST" -type f -exec chmod 644 {} \;
            log_success "Backup restaurado!"
        else
            log_error "Erro ao restaurar backup"
            return 1
        fi
    else
        log_error "Nenhum backup encontrado!"
        return 1
    fi
}

# Aplicar nova versão
aplicar_versao() {
    local source_dir="$1"
    
    log_info "Aplicando nova versão..."
    
    # Criar diretório de destino se não existir
    mkdir -p "$(dirname "$DEST")"
    
    # Remover versão atual e copiar nova
    rm -rf "$DEST"
    if cp -a "$source_dir" "$DEST"; then
        chown -R $USER:$GROUP "$DEST"
        find "$DEST" -type d -exec chmod 755 {} \;
        find "$DEST" -type f -exec chmod 644 {} \;
        log_success "Nova versão aplicada com sucesso!"
    else
        log_error "Erro ao aplicar nova versão"
        return 1
    fi
}

# Reiniciar containers Docker
reiniciar_docker() {
    local docker_dir="$DEST/DOCKER-FILES"
    
    if [ -f "$docker_dir/docker-compose.yml" ]; then
        log_info "Reiniciando containers Docker..."
        cd "$docker_dir"
        if docker-compose down && docker-compose up -d --build; then
            log_success "Containers Docker reiniciados com sucesso."
        else
            log_warning "Erro ao reiniciar containers Docker"
        fi
    else
        log_warning "Arquivo docker-compose.yml não encontrado em $docker_dir"
    fi
}

# Limpar arquivos temporários
cleanup() {
    log_info "Limpando arquivos temporários..."
    rm -rf "$TEMP_DIR"
}

# Função principal de atualização
atualizar_versao() {
    log_info "Iniciando processo de atualização..."
    
    # Verificar dependências
    check_dependencies
    
    # Obter última versão
    local version_info
    version_info=$(get_latest_version)
    if [ $? -ne 0 ]; then
        log_error "Falha ao obter última versão"
        exit 1
    fi
    
    local version=$(echo "$version_info" | cut -d'|' -f1)
    log_info "Última versão disponível: $version"
    
    # Fazer backup
    backup_atual
    
    # Baixar e descompactar
    local source_dir
    source_dir=$(download_and_extract "$version_info")
    if [ $? -ne 0 ]; then
        log_error "Falha ao baixar/descompactar versão"
        exit 1
    fi
    
    # Aplicar nova versão
    aplicar_versao "$source_dir"
    if [ $? -ne 0 ]; then
        log_error "Falha ao aplicar nova versão"
        log_info "Tente restaurar o backup com: $0 restaurar"
        exit 1
    fi
    
    # Reiniciar Docker
    reiniciar_docker
    
    # Limpar
    cleanup
    
    log_success "Atualização concluída com sucesso!"
}

# Função para listar backups disponíveis
listar_backups() {
    local backup_base="$BASE_DIR"
    local backups=($(ls -1d "$backup_base"/backup_farmacia_* 2>/dev/null | sort -r))
    
    if [ ${#backups[@]} -eq 0 ]; then
        log_info "Nenhum backup encontrado"
        return
    fi
    
    log_info "Backups disponíveis:"
    local i=1
    for backup in "${backups[@]}"; do
        local date=$(basename "$backup" | sed 's/backup_farmacia_//')
        echo "$i - $date"
        i=$((i+1))
    done
}

# Função para restaurar backup específico
restaurar_backup_especifico() {
    local backup_base="$BASE_DIR"
    local backups=($(ls -1d "$backup_base"/backup_farmacia_* 2>/dev/null | sort -r))
    
    if [ ${#backups[@]} -eq 0 ]; then
        log_error "Nenhum backup encontrado"
        exit 1
    fi
    
    listar_backups
    read -p "Digite o número do backup que deseja restaurar: " NUM
    
    if ! [[ "$NUM" =~ ^[0-9]+$ ]] || [ "$NUM" -lt 1 ] || [ "$NUM" -gt ${#backups[@]} ]; then
        log_error "Número inválido!"
        exit 1
    fi
    
    local selected_backup="${backups[$((NUM-1))]}"
    log_info "Restaurando backup: $(basename "$selected_backup")"
    
    rm -rf "$DEST"
    if cp -a "$selected_backup" "$DEST"; then
        chown -R $USER:$GROUP "$DEST"
        find "$DEST" -type d -exec chmod 755 {} \;
        find "$DEST" -type f -exec chmod 644 {} \;
        log_success "Backup restaurado com sucesso!"
    else
        log_error "Erro ao restaurar backup"
        exit 1
    fi
}

# Menu principal
case "$1" in
    atualizar)
        atualizar_versao
        ;;
    restaurar)
        restaurar_backup
        ;;
    listar-backups)
        listar_backups
        ;;
    restaurar-especifico)
        restaurar_backup_especifico
        ;;
    *)
        echo "Uso: $0 {atualizar|restaurar|listar-backups|restaurar-especifico}"
        echo ""
        echo "Comandos disponíveis:"
        echo "  atualizar           - Baixar e aplicar a última versão do GitHub"
        echo "  restaurar           - Restaurar o último backup criado"
        echo "  listar-backups      - Listar todos os backups disponíveis"
        echo "  restaurar-especifico - Restaurar um backup específico"
        ;;
esac 