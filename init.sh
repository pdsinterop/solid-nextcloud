#!/bin/bash
php console.php maintenance:install --admin-user alice --admin-pass alice123
php console.php status
php console.php app:enable solid
sed -i '61 i\  RewriteRule ^\\.well-known/openid-configuration /apps/solid/openid [R=302,L]' .htaccess
sed -i "25 i\    1 => 'server'," config/config.php
echo configured
