#!/bin/bash
export PHP_MEMORY_LIMIT="512M"
php console.php maintenance:install --admin-user alice --admin-pass alice123
php console.php status
php console.php app:enable solid
php console.php config:system:set trusted_domains 1 --value=server
php console.php config:system:set trusted_domains 2 --value=nextcloud.local
php console.php config:system:set trusted_domains 3 --value=thirdparty
# set 'tester' and 'https://tester' as allowed clients for the test suite to run
php console.php user:setting alice solid allowedClients '["f5d1278e8109edd94e1e4197e04873b9", "2e5cddcf0f663544e98982931e6cc5a6"]'
echo configured
