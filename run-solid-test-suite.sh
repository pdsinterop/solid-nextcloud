#!/bin/bash

# Run the Solid test-suite
docker network create testnet
docker build -t nextcloud-server https://github.com/solid/test-suite.git#master:/servers/nextcloud-server
docker build -t webid-provider https://github.com/solid/test-suite.git#master:/testers/webid-provider
docker run -d --name server --network=testnet nextcloud-server
wget -O /tmp/env-vars-for-test-image.list https://raw.githubusercontent.com/solid/test-suite/master/servers/nextcloud-server/env.list
until docker run --rm --network=testnet webid-provider curl -kI https://server 2> /dev/null > /dev/null
do
  echo Waiting for server to start, this can take up to a minute ...
  docker ps -a
  docker logs server || true
  sleep 1
done
docker ps -a
docker logs server
docker run --rm --network=testnet --env-file /tmp/env-vars-for-test-image.list webid-provider
rm /tmp/env-vars-for-test-image.list
docker stop server
docker rm server
docker network remove testnet