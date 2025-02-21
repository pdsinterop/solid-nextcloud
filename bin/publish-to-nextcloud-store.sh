#!/usr/bin/env bash

set -o errexit -o errtrace -o nounset -o pipefail

# ==============================================================================
#                           PUBLISH TO NEXTCLOUD STORE
# ------------------------------------------------------------------------------
# This script takes all steps needed to publish a release of solid-nextcloud to
# the Nextcloud App store. This consists of:
#
# 1. Building a package
#    - checking out the created tag
#    - installing the project dependencies
#    - creating a tarball from the project and its dependencies
# 2. Deploying the package'
#    - getting the URL from GitHub to where the tarball should be uploaded
#    - uploading the tarball to GitHub
# 3. Creating a release in the Nextcloud App store'
#    - creating a signature file for the tarball
#    - publishing the tarball to the Nextcloud store
#
# There are various assumption made by this script:
#
# - A git tag has been created
# - A GitHub Release has been created (so a Release Page exists where a package can be uploaded to)
# - The `transfer/solid.key` and `transfer/solid.crt` files exist (both are needed to create a signature)
#
# ------------------------------------------------------------------------------
# Usage:
#     $0 <subject-path> <version>
#
# Where:
#     <subject-path>    The path to where the project repository is located
#     <version>         The version for which a release should be published
#
# Usage example:
#
#     bash bin/publish_to_nextcloud_store.sh "${PWD}" 'v0.9.0'
#
# ==============================================================================

# Allow overriding the executables used in this script
: "${DOCKER:=docker}"
: "${GIT:=git}"

# @FIXME: Add functions to validate required tools are installed

publish_to_nextcloud_store() {
    local sSourceDirectory sVersion

    readonly sSourceDirectory="${1?Two parameters required: <subject-path> <version>}"
    readonly sVersion="${2?Two parameters required: <subject-path> <version>}"

    checkoutTag() {
        local sVersion

        readonly sVersion="${1?One parameter required: <version>}"

        "${GIT}" checkout "${sVersion}"
    }

    installDependencies() {
        local sDockerFile sSourceDirectory

        readonly sDockerFile="${1?Two parameters required: <docker-file> <subject-path>}"
        readonly sSourceDirectory="${2?Two parameters required: <docker-file> <subject-path>}"

        "${DOCKER}" run \
            -it \
            --network=host \
            --rm \
            --volume "${sSourceDirectory}/solid:/app" \
            --volume ~/.cache/composer/:/root/composer/ \
            --workdir /app \
            "${sDockerFile}" \
            bash -c 'php --version && composer --version \
                && COMPOSER_CACHE_DIR=/root/composer/ composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist \
            '
    }

    checkoutTag "${sVersion}"
    # @TODO: The PHP version should either be a param, parsed from composer.json or both!
    #        (Allow to be set but used parsed value as default...)
    installDependencies 'composer:2.2.17' "${sSourceDirectory}"
}

if [ -n "${BASH_SOURCE:-}" ] && [ "${BASH_SOURCE[0]}" != "${0}" ]; then
    export publish_to_nextcloud_store
else
    publish_to_nextcloud_store "${@}"
fi
