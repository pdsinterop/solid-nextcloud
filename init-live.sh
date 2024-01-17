#!/bin/bash
export PHP_MEMORY_LIMIT="512M"
php console.php maintenance:install --admin-user admin --admin-pass $MARIADB_ROOT_PASSWORD --database "mysql" --database-name "nextcloud" --database-user "root" --database-pass "$MARIADB_ROOT_PASSWORD" --database-host "127.0.0.1"
php console.php status
php console.php app:enable solid
php console.php config:system:set trusted_domains 1 --value=server
php console.php config:system:set trusted_domains 2 --value=nextcloud.local
php console.php config:system:set trusted_domains 3 --value=thirdparty
php console.php config:system:set trusted_domains 4 --value=$HOST
echo configured
