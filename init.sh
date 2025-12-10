#!/bin/bash
export PHP_MEMORY_LIMIT="512M"
php console.php maintenance:install --admin-user alice --admin-pass alice123
php console.php status
php console.php app:enable solid
php console.php config:system:set trusted_domains 1 --value=server
php console.php config:system:set trusted_domains 2 --value=nextcloud.local
php console.php config:system:set trusted_domains 3 --value=thirdparty
# set 'tester' and 'https://tester' as tyrusted apps for the test suite to run
php console.php config:app:set solid trustedApps --type=string --value='["https://tester", "tester"]'

echo configured
mkdir -p /var/www/html/data/files_trashbin/versions