#!/bin/bash
set -e

# Run the Solid test-suite
docker network create testnet

# Build and start Nextcloud server with code from current repo contents:
docker build -t server .
docker run -d --name server --network=testnet server

docker pull solidtestsuite/webid-provider-tests
docker pull michielbdejong/nextcloud-cookie

until docker run --rm --network=testnet solidtestsuite/webid-provider-tests curl -kI https://server 2> /dev/null > /dev/null
do
  echo Waiting for server to start, this can take up to a minute ...
  docker ps -a
  docker logs server || true
  sleep 1
done
docker ps -a
docker logs server
echo Running init script for Nextcloud server ...
docker exec -u www-data -it -e SERVER_ROOT=https://server server sh /init.sh
docker exec -u root -it server service apache2 reload

echo Getting cookie...
export COOKIE="`docker run --cap-add=SYS_ADMIN --network=testnet --env-file ./env-vars-for-test-image.list michielbdejong/nextcloud-cookie`"
echo "Running solidtestsuite/webid-provider-tests with cookie $COOKIE"
docker run --rm --network=testnet --env COOKIE="$COOKIE" --env-file ./env-vars-for-test-image.list solidtestsuite/webid-provider-tests
# rm /tmp/env-vars-for-test-image.list
# docker stop server
# docker rm server
# docker network remove testnet
