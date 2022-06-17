#!/bin/bash
export PHP_MEMORY_LIMIT="512M"
php console.php maintenance:install --admin-user admin --admin-pass $MARIADB_ROOT_PASSWORD --database "mysql" --database-name "nextcloud" --database-user "root" --database-pass "$MARIADB_ROOT_PASSWORD" --database-host "127.0.0.1"
php console.php status
php console.php app:enable solid
sed -i '96 i\  RewriteRule ^\\.well-known/openid-configuration /apps/solid/openid [PT,L]' /var/www/html/.htaccess
sed -i "25 i\    1 => 'server'," /var/www/html/config/config.php
sed -i "26 i\    2 => 'nextcloud.local'," /var/www/html/config/config.php
sed -i "27 i\    3 => 'thirdparty'," /var/www/html/config/config.php
sed -i "28 i\    4 => '$HOST'," /var/www/html/config/config.php
echo configured
