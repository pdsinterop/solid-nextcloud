#!/usr/bin/env bash

set -o errexit -o errtrace -o nounset -o pipefail

publish_to_nextcloud_store() {

}

if [ -n "${BASH_SOURCE:-}" ] && [ "${BASH_SOURCE[0]}" != "${0}" ]; then
    export publish_to_nextcloud_store
else
    publish_to_nextcloud_store "${@}"
fi
