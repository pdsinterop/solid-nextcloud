#!/bin/bash
export PHP_MEMORY_LIMIT="512M"
php console.php maintenance:install --admin-user alice --admin-pass alice123
php console.php status
php console.php app:enable solid
sed -i '61 i\  RewriteRule ^\\.well-known/openid-configuration /apps/solid/openid [R=302,L]' /var/www/html/.htaccess
sed -i "25 i\    1 => 'server'," /var/www/html/config/config.php
sed -i "26 i\    2 => 'nextcloud.local'," /var/www/html/config/config.php
sed -i "27 i\    3 => 'thirdparty'," /var/www/html/config/config.php
echo configured
