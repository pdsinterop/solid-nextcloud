#!/usr/bin/env bash

set -o errexit  # Exit script when a command exits with non-zero status.
#set -o errtrace # Exit on error inside any functions or sub-shells.
set -o nounset  # Exit script on use of an undefined variable.
#set -o pipefail # Return exit status of the last command in the pipe that exited with a non-zero exit code

if [ -z "${NEXTCLOUD_VERSION}" ]; then
    echo >&2 'The "NEXTCLOUD_VERSION" variable MUST be set during build: docker build --build-arg "NEXTCLOUD_VERSION=..."'
    exit 65
else
    echo "NEXTCLOUD_VERSION is set to '${NEXTCLOUD_VERSION}'"
fi

PHP_VERSION="${PHP_VERSION:-$(php -r 'echo PHP_VERSION;')}"
PHP_MAJOR="${PHP_VERSION%%.*}"
PHP_MINOR="$(echo "${PHP_VERSION}" | awk -F. '{print $2}')"

if [ -z "${XDEBUG_VERSION:-}" ]; then
    if [ "$PHP_MAJOR" -eq 8 ]; then
        XDEBUG_VERSION=3.4.3
    elif [ "$PHP_MAJOR" -eq 7 ]; then
        if [ "$PHP_MINOR" -ge 2 ] && [ "$PHP_MINOR" -le 4 ]; then
            XDEBUG_VERSION=3.1.6
        elif [ "$PHP_MINOR" -eq 1 ]; then
            XDEBUG_VERSION=2.9.8
        elif [ "$PHP_MINOR" -eq 0 ]; then
            XDEBUG_VERSION=2.7.2
        else
            echo >&2 "Unsupported PHP 7 minor version: $PHP_MINOR"
            exit 66
        fi
    elif [ "$PHP_MAJOR" -eq 5 ]; then
        if [ "$PHP_MINOR" -ge 5 ] && [ "$PHP_MINOR" -le 6 ]; then
            XDEBUG_VERSION=2.5.5
        elif [ "$PHP_MINOR" -eq 4 ]; then
            XDEBUG_VERSION=2.4.1
        else
            echo >&2 "Unsupported PHP 5 minor version: $PHP_MINOR"
            exit 67
        fi
    else
        echo >&2 "Unsupported PHP version: ${PHP_VERSION}"
        exit 68
    fi
fi

echo "${XDEBUG_VERSION}" > /xdebug.version
