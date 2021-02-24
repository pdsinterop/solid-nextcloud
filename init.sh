#!/bin/bash
php console.php maintenance:install --admin-user alice --admin-pass alice123
php console.php status
php console.php app:enable solid
sed -i '61 i\  RewriteRule ^\\.well-known/openid-configuration /apps/solid/openid [R=302,L]' .htaccess
echo 'SetEnv AccessControlAllowOrigin="*"' >> .htaccess
echo 'SetEnvIf Origin "^(.*)$" AccessControlAllowOrigin=$0' >> .htaccess
echo 'Header add Access-Control-Allow-Origin %{AccessControlAllowOrigin}e env=AccessControlAllowOrigin' >> .htaccess
echo 'Header add Access-Control-Allow-Credentials true' >> .htaccess
echo 'Header add Access-Control-Allow-Headers "*, allow, authorization, content-type, dpop"' >> .htaccess
echo 'Header add Access-Control-Allow-Methods "GET, PUT, POST, OPTIONS, DELETE, PATCH"' >> .htaccess
echo 'Header add Accept-Patch: application/sparql-update' >> .htaccess

sed -i "25 i\    1 => 'server'," config/config.php
sed -i "26 i\    2 => 'thirdparty'," config/config.php
echo configured
