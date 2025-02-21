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
#     $0 <version>
#
# Where:
#     <version>   The version for which a release should be published
#
# Usage example:
#
#     bash bin/publish_to_nextcloud_store.sh 'v0.9.0'
#
# ==============================================================================

# Allow overriding the executables used in this script
: "${GIT:=git}"

# @FIXME: Add functions to validate required tools are installed

publish_to_nextcloud_store() {
    local sVersion
    readonly sVersion="${1?One parameters required: <version>}"

    checkoutTag() {
        local sVersion

        readonly sVersion="${1?One parameter required: <version>}"

        "${GIT}" checkout "${sVersion}"
    }

    checkoutTag "${sVersion}"
}

if [ -n "${BASH_SOURCE:-}" ] && [ "${BASH_SOURCE[0]}" != "${0}" ]; then
    export publish_to_nextcloud_store
else
    publish_to_nextcloud_store "${@}"
fi
