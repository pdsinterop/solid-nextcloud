#!/bin/bash

# when you ssh into a new empty ubuntu server:
#
# git clone https://github.com/pdsinterop/solid-nextcloud
# cd solid-nextcloud
# export HOST=your.host.com
# export MARIADB_ROOT_PASSWORD=...
# /bin/bash ./setup-test-server.sh
#
# that runs this script :)
# you will get some interactive questions from LetsEncrypt certbot.

echo Setting up full nextcloud-solid server for $HOST

apt update
apt install -y docker certbot
certbot certonly --standalone
mkdir -p /root/tls
cp /etc/letsencrypt/live/$HOST/fullchain.pem /root/tls/server.cert
cp /etc/letsencrypt/live/$HOST/privkey.pem /root/tls/server.key
cd /root/solid-nextcloud
docker build -t solid-nextcloud .
docker build -t pubsub-server https://github.com/pdsinterop/php-solid-pubsub-server.git#main


docker run -d --network=host -e MARIADB_ROOT_PASSWORD=$MARIADB_ROOT_PASSWORD --name=db mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
docker run -d --name=nextcloud --network=host -v /root/tls:/tls solid-nextcloud
# docker run -d --name-pubsub --network=host -v /root/tls:/tls pubsub-server

sleep 15

docker exec -u www-data -it -e SERVER_ROOT=https://$HOST nextcloud sh /init.sh
docker exec -u www-data -it -e SERVER_ROOT=https://$HOST nextcloud sed -i "28 i\    4 => '$HOST'," /var/www/html/config/config.php
