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

# Iniciar Apache
apache2-foreground
