#!/bin/bash
set -e

function setup {
  docker network create testnet
  docker build -t solid-nextcloud .
  docker build -t pubsub-server  https://github.com/pdsinterop/php-solid-pubsub-server.git#main
  docker pull michielbdejong/nextcloud-cookie
  docker pull solidtestsuite/webid-provider-tests:v2.0.3
  docker tag solidtestsuite/webid-provider-tests:v2.0.3 webid-provider-tests
  docker pull solidtestsuite/solid-crud-tests:v5.1.0
  docker tag solidtestsuite/solid-crud-tests:v5.1.0 solid-crud-tests
  docker pull solidtestsuite/web-access-control-tests:v6.0.0
  docker tag solidtestsuite/web-access-control-tests:v6.0.0 web-access-control-tests
}
function teardown {
  docker stop `docker ps --filter network=testnet -q`
  docker rm `docker ps --filter network=testnet -qa`
  docker network remove testnet
}

function startPubSub {
  docker run -d --name pubsub --network=testnet pubsub-server
}

function startSolidNextcloud {
  docker run -d --name $1 --network=testnet --env-file ./env-vars-$1.list solid-nextcloud
  until docker run --rm --network=testnet solidtestsuite/webid-provider-tests curl -kI https://$1 2> /dev/null > /dev/null
  do
    echo Waiting for $1 to start, this can take up to a minute ...
    docker ps -a
    docker logs $1
    sleep 1
  done

  docker logs $1
  echo Running init script for Nextcloud $1 ...
  docker exec -u www-data -it -e SERVER_ROOT=https://$1 $1 sh /init.sh
  docker exec -u root -it $1 service apache2 reload
  echo Getting cookie for $1...
  export COOKIE_$1="`docker run --cap-add=SYS_ADMIN --network=testnet --env-file ./env-vars-$1.list michielbdejong/nextcloud-cookie`"
}

function runTests {
  echo "Running $1 tests against server with cookie $COOKIE_server"
  docker run --rm --network=testnet \
    --env COOKIE="$COOKIE_server" \
    --env COOKIE_ALICE="$COOKIE_server" \
    --env COOKIE_BOB="$COOKIE_thirdparty" \
    --env-file ./env-vars-testers.list $1-tests
}

# ...
teardown || true
setup
startPubSub
startSolidNextcloud server
startSolidNextcloud thirdparty
runTests webid-provider
runTests solid-crud
runTests web-access-control
teardown
