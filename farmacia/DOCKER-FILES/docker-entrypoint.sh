#!/bin/bash

set -e


# Ajusta permissões em runtime (para volumes)

chown -R www-data:www-data /var/www/html

find /var/www/html -type d -exec chmod 755 {} \;

find /var/www/html -type f -exec chmod 644 {} \;


# Inicia serviços

service php${PHP_VERSION}-fpm start

exec nginx -g "daemon off;"
