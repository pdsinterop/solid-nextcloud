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
# @CHECKME: The need for the `transfer/solid.crt` file is taken from the original deploy instructions,
#           But it is not _explicitly_ used in this script. Is the need implicit? Or incorrect?
# ------------------------------------------------------------------------------
# Usage:
#     $0 <subject-path> <version>
#
# Where:
#     <subject-path>    The path to where the project repository is located
#     <version>         The version for which a release should be published
#     <github-token>    A token used to make GET and POST calls to the GitHub API
#     <nextcloud-token> The access token used to POST to the Nextcloud Apps Store API
#
# Usage example:
#
#     bash bin/publish_to_nextcloud_store.sh "${PWD}" 'v0.9.0'
#
# ==============================================================================

# Allow overriding the executables used in this script
: "${CURL:=curl}"
: "${DOCKER:=docker}"
: "${GIT:=git}"
: "${JQ:=jq}"
: "${OPENSSL:=openssl}"
: "${TAR:=tar}"

# @FIXME: Add functions to validate required tools are installed

print_usage() {
    local sScript sUsage

    sScript="$(basename "$0")"
    readonly sScript

    sUsage="$(grep '^#/' <"$0" | cut -c4-)"
    readonly sUsage

    echo -e "${sUsage//\$0/${sScript}}"
}

publish_to_nextcloud_store() {
    checkoutTag() {
        local sVersion

        readonly sVersion="${1?One parameter required: <version>}"

        "${GIT}" checkout "${sVersion}"
    }

    createSignature() {
        local sKeyFile sTarball

        readonly sTarball="${1?Two parameter required: <tarball-name> <key-file>}"
        readonly sKeyFile="${2?Two parameter required: <tarball-name> <key-file>}"

        "${OPENSSL}" dgst -sha512 -sign "${sKeyFile}" "${sTarball}" \
            | "${OPENSSL}" base64 \
            | tr -d "\n"
    }

    createTarball() {
        local sSourceDirectory sTarball

        readonly sSourceDirectory="${1?Two parameters required: <source-path> <tarball-name>}"
        readonly sTarball="${2?Two parameters required: <source-path> <tarball-name>}"

        "${TAR}" --directory="${sSourceDirectory}" --create --file "${sTarball}" --gzip "solid"
    }

    fetchGitHubUploadUrl() {
        local sGithubToken sVersion

        readonly sVersion="${1?Two parameters required: <version> <github-token>}"
        readonly sGithubToken="${2?Two parameters required: <version> <github-token>}"

        "${CURL}" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${sGithubToken}" \
            --silent \
            "https://api.github.com/repos/pdsinterop/solid-nextcloud/releases/tags/${sVersion}" \
            | "${JQ}" --raw-output '.upload_url' \
            | cut -d '{' -f 1
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

    publishToNextcloud() {
        local sDownloadUrl sJson sNextcloudToken sSignatureFile

        readonly sDownloadUrl="${1?Three parameters required: <download-url> <signature-file> <nextcloud-token>}"
        readonly sSignatureFile="${1?Three parameters required: <download-url> <signature-file> <nextcloud-token>}"
        readonly sNextcloudToken="${1?Three parameters required: <download-url> <signature-file> <nextcloud-token>}"

        sJson="$(
            printf '{"download":"%s", "signature": "%s"}' \
                "${sDownloadUrl}" \
                "$(cat "${sSignatureFile}")"
        )"
        readonly sJson

        "${CURL}" \
            --data "${sJson}" \
            --header "Authorization: Token ${sNextcloudToken}" \
            --header "Content-Type: application/json" \
            --request POST \
            --silent \
            'https://apps.nextcloud.com/api/v1/apps/releases'
    }

    uploadAssetToGitHub() {
        local sGithubToken sTarball sUrl

        readonly sUrl="${1?Three parameters required: <upload-url> <github-token> <tarbal-name>}"
        readonly sGithubToken="${2?Three parameters required: <upload-url> <github-token> <tarbal-name>}"
        readonly sTarball="${3?Three parameters required: <upload-url> <github-token> <tarbal-name>}"

        "${CURL}" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${sGithubToken}" \
            --header "Content-Length: $(stat --printf="%s" "${sTarball}")" \
            --header "Content-Type: $(file -b --mime-type "${sTarball}")" \
            --header "X-GitHub-Api-Version: 2022-11-28" \
            --request POST \
            --silent \
            --upload-file "${sTarball}" \
            "${sUrl}?name=${sTarball}" \
            | "${JQ}" --raw-output '.browser_download_url'
    }

    local sDownloadUrl sGithubToken sKeyFile sNextcloudToken sSignatureFile sSourceDirectory sTarball sUploadUrl sVersion

    readonly sSourceDirectory="${1?Four parameters required: <subject-path> <version> <github-token> <nextcloud-token>}"
    readonly sVersion="${2?Four parameters required: <subject-path> <version> <github-token> <nextcloud-token>}"
    readonly sGithubToken="${3?Four parameters required: <subject-path> <version> <github-token> <nextcloud-token>}"
    readonly sNextcloudToken="${4?Four parameters required: <subject-path> <version> <github-token> <nextcloud-token>}"

    # @FIXME: This just hard-codes the path, either the path or the contents of
    #         the file should be passed in as parameter
    sKeyFile="$(dirname "$(dirname "$(realpath "$0")")")/transfer/solid.key"
    readonly sKeyFile
    readonly sSignatureFile="signature.base64"
    readonly sTarball='solid.tar.gz'

    checkoutTag "${sVersion}"
    # @TODO: The PHP version should either be a param, parsed from composer.json or both!
    #        (Allow to be set but used parsed value as default...)
    installDependencies 'composer:2.2.17' "${sSourceDirectory}"
    createTarball "${sSourceDirectory}" "${sTarball}"

    sUploadUrl="$(fetchGitHubUploadUrl "${sVersion}" "${sGithubToken}")"
    readonly sUploadUrl
    sDownloadUrl="$(uploadAssetToGitHub "${sUploadUrl}" "${sGithubToken}" "${sTarball}")"

    createSignature "${sTarball}" "${sKeyFile}" > "${sSignatureFile}"
    publishToNextcloud "${sDownloadUrl}" "${sSignatureFile}" "${sNextcloudToken}"

    # @FIXME: Add an exit trap to remove "${sSignatureFile}"
}

if [ -n "${BASH_SOURCE:-}" ] && [ "${BASH_SOURCE[0]}" != "${0}" ]; then
    export publish_to_nextcloud_store
else
    publish_to_nextcloud_store "${@}"
fi
