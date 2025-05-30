#!/bin/bash

# Definir www-data como dono de todos os arquivos
chown -R www-data:www-data /var/www/html

# Definir permissões para diretórios
find /var/www/html -type d -exec chmod 755 {} \;

# Definir permissões para todos os arquivos
find /var/www/html -type f -exec chmod 644 {} \;

# Garantir que diretórios específicos sejam graváveis
chmod -R 775 /var/www/html/LOG
chmod -R 775 /var/www/html/logs
chmod -R 775 /var/www/html/images
chmod -R 775 /var/www/html/css
chmod -R 775 /var/www/html/vendor
chmod -R 775 /var/www/html/templates

# Garantir que o Apache possa escrever em diretórios temporários
chmod -R 775 /var/lib/php/sessions

# Verificar SELinux (se estiver ativo)
if command -v getenforce >/dev/null 2>&1; then
    if [ "$(getenforce)" != "Disabled" ]; then
        chcon -R -t httpd_sys_content_t /var/www/html
        chcon -R -t httpd_sys_rw_content_t /var/www/html/LOG
        chcon -R -t httpd_sys_rw_content_t /var/www/html/vendor
        chcon -R -t httpd_sys_rw_content_t /var/lib/php/sessions
    fi
fi

echo "Permissões ajustadas com sucesso!" 