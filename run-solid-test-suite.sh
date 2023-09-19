#!/usr/bin/env bash

set -e

# Note that .github/workflows/ci.yml does not use this, this function is just for manual runs of this script.
# You can pick different values for the NEXTCLOUD_VERSION build arg, as required:
function setup {
  docker build -t pubsub-server  https://github.com/pdsinterop/php-solid-pubsub-server.git#main
  docker build -t solid-nextcloud --build-arg NEXTCLOUD_VERSION=25 .

  docker network create testnet

  docker pull michielbdejong/nextcloud-cookie
  docker pull solidtestsuite/solid-crud-tests:v7.0.5
  docker pull solidtestsuite/web-access-control-tests:v7.1.0
  docker pull solidtestsuite/webid-provider-tests:v2.1.1

  docker tag solidtestsuite/solid-crud-tests:v7.0.5 solid-crud-tests
  docker tag solidtestsuite/web-access-control-tests:v7.1.0 web-access-control-tests
  docker tag solidtestsuite/webid-provider-tests:v2.1.1 webid-provider-tests
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
  docker run -d --name "$1" --network=testnet --env-file "./env-vars-$1.list" "${2:-solid-nextcloud}"
  until docker run --rm --network=testnet solidtestsuite/webid-provider-tests curl -kI "https://$1" 2> /dev/null > /dev/null
  do
    echo Waiting for "$1" to start, this can take up to a minute ...
    docker ps -a
    docker logs "$1"
    sleep 1
  done

  docker logs "$1"
  echo "Running init script for Nextcloud $1 ..."
  docker exec -u www-data -i -e SERVER_ROOT="https://$1" "$1" sh /init.sh
  docker exec -u root -i "$1" service apache2 reload
  echo Getting cookie for "$1"...
  export COOKIE_$1="$(docker run --cap-add=SYS_ADMIN --network=testnet --env-file "./env-vars-$1.list" michielbdejong/nextcloud-cookie)"
}

function runTests {
  echo "Running $1 tests against server with cookie $COOKIE_server"
  docker run --rm --network=testnet \
    --name tester \
    --env COOKIE="$COOKIE_server" \
    --env COOKIE_ALICE="$COOKIE_server" \
    --env COOKIE_BOB="$COOKIE_thirdparty" \
    --env-file ./env-vars-testers.list \
    "$1-tests"
}

run_solid_test_suite() {
    # ...
    teardown || true
    setup
    startPubSub
    startSolidNextcloud server
    startSolidNextcloud thirdparty
    runTests webid-provider
    runTests web-access-control
    runTests solid-crud
    teardown
}

if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
  run_solid_test_suite "${@}"
else
    export -f run_solid_test_suite
    export -f runTests
    export -f setup
    export -f startPubSub
    export -f startSolidNextcloud
    export -f teardown
fi
