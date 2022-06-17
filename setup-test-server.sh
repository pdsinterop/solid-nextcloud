#!/bin/bash

# when you ssh into a new empty ubuntu server:
#
# git clone https://github.com/pdsinterop/solid-nextcloud
# cd solid-nextcloud
# /bin/bash ./setup-test-server.sh
#
# that runs this script :)

apt update
apt install -y docker certbot
certbot certonly --standalone
docker build -t solid-nextcloud .
docker build -t pubsub-server  https://github.com/pdsinterop/php-solid-pubsub-server.git#main
