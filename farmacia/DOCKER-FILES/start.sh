#!/bin/bash

# Garantir que o diretório vendor existe e tem as permissões corretas
mkdir -p /var/www/html/vendor
chown -R www-data:www-data /var/www/html/vendor

# Criar composer.json se não existir
if [ ! -f /var/www/html/composer.json ]; then
    echo '{"require": {}}' > /var/www/html/composer.json
    chown www-data:www-data /var/www/html/composer.json
fi

# Instalar dependências como www-data se necessário
if [ ! -d /var/www/html/vendor/phpoffice ]; then
    cd /var/www/html && \
    su -s /bin/bash -c "composer require phpoffice/phpspreadsheet" www-data
fi

# Instalar JWT se necessário
if [ ! -d /var/www/html/vendor/firebase ]; then
    cd /var/www/html && \
    su -s /bin/bash -c "composer require firebase/php-jwt" www-data
fi

# Inicializar serviço cron
echo "🕐 Iniciando serviço cron..."
service cron start

# Verificar se o cron está rodando
if service cron status > /dev/null 2>&1; then
    echo "✅ Serviço cron iniciado com sucesso"
    echo "📅 Cron jobs ativos:"
    crontab -l
else
    echo "⚠️  Aviso: Não foi possível verificar o status do cron"
fi

# Iniciar Apache
echo "🌐 Iniciando Apache..."
apache2-foreground
