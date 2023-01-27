#!/usr/bin/env bash

set -o errexit -o nounset

# Allow overriding the executables used in this script
: "${CURL:=curl}"
: "${DOCKER:=docker}"
: "${GIT:=git}"
: "${OPENSSL:=openssl}"
: "${TAR:=tar}"

: "${PROJECT_DIR:="$(dirname "$(dirname "$(realpath "$0")")")"}"

publish() {
    local githubToken keyFile nextcloudToken  signatureFile sourceDirectory tarball version

    readonly sourceDirectory="${1?Four parameters required: <subject-path> <version> <nextcloud-token> <github-token>}"
    readonly version="${2?Four parameters required: <subject-path> <version> <nextcloud-token> <github-token>}"
    readonly nextcloudToken="${3?Four parameters required: <subject-path> <version> <nextcloud-token> <github-token>}"
    readonly githubToken="${4?Four parameters required: <subject-path> <version> <nextcloud-token> <github-token>}"

    readonly keyFile="${PROJECT_DIR}/transfer/solid.key"
    readonly signatureFile="${PROJECT_DIR}/signature.base64"
    readonly tarball='solid.tar.gz'

    createTag() {
        local version

        readonly version="${1?One parameter required: <version>}"

        "${GIT}" tag --annotate --message="Release ${version}" --sign "${version}"
        "${GIT}" push --tags
    }

    updateLocalReleaseBranch() {
        local branch

        readonly branch="${1?One parameter required: <branch-name>}"

        "${GIT}" checkout "${branch}"
        "${GIT}" pull
        "${GIT}" merge main
    }

    updateRemoteReleaseBranch() {
      "${GIT}" commit -am "build" # (at least `solid.tar.gz` and `vendor/composer/installed.php` will have changed)
      git push
      "${GIT}" checkout -
    }

    installDependencies() {
        local sourceDirectory

        readonly sourceDirectory="${1?One parameter required: <subject-path>}"

        "${DOCKER}" run \
            -it \
            --rm \
            --volume ~/.cache/composer/:/root/composer/ \
            --volume "${sourceDirectory}/solid:/app" \
            --workdir /app \
            php:8.0 \
            bash -c 'curl -s https://getcomposer.org/installer | php \
                && mv composer.phar /usr/local/bin/composer \
                && apt update \
                && apt install -y git zip \
                && COMPOSER_CACHE_DIR=/root/composer/ composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist \
            '
    }

    createTarball() {
        local sourceDirectory tarball

        readonly sourceDirectory="${1?Two parameters required: <subject-path> <tarball-name>}"
        readonly tarball="${2?Two parameters required: <subject-path> <tarball-name>}"

        "${TAR}" --create --file "${tarball}" --gzip "${sourceDirectory}/solid"
    }

    createGithubRelease() {
        local description githubToken json tag version

        readonly version="${1?Two parameters required: <version> <github-token>}"
        readonly githubToken="${2?Two parameters required: <version> <github-token>}"

        readonly description="Release ${version}"
        readonly tag="${version}"

        json="$(printf '{"tag_name":"%s","target_commitish":"%s","name":"%s","body":"%s","draft":false,"prerelease":false,"generate_release_notes":true}' \
            "${tag}" \
            "main" \
            "${version}" \
            "${description}" \
        )"

        "${CURL}" \
              --data "${json}" \
              --header "Accept: application/vnd.github+json" \
              --header "Authorization: Bearer ${githubToken}" \
              --request POST \
              https://api.github.com/repos/pdsinterop/solid-nextcloud/releases
    }

    uploadAssetToGithub() {
        local githubToken tarball version

        readonly version="${1?Three parameters required: <version> <github-token> <tarbal-name>}"
        readonly githubToken="${2?Three parameters required: <version> <github-token> <tarbal-name>}"
        readonly tarball="${3?Three parameters required: <version> <github-token> <tarbal-name>}"

        "${CURL}" \
            --data "@${tarball}" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer ${githubToken}" \
            --request POST \
            "https://uploads.github.com/repos/pdsinterop/solid-nextcloud/releases/${version}/assets?name=${tarball}"
    }

    createSignature() {
        local tarball

        readonly tarball="${1?One parameter required: <tarball-name>}"

        "${OPENSSL}" dgst -sha512 -sign "${keyFile}" "${tarball}" \
            | "${OPENSSL}" base64 \
            | tr -d "\n" \
            > "${signatureFile}"
    }

    publishToNextcloud() {
        local downloadUrl json tarball version

        readonly version="${1?Two parameters required: <version> <tarball-name>}"
        readonly tarball="${2?Two parameters required: <version> <tarball-name>}"

        readonly downloadUrl="https://github.com/pdsinterop/solid-nextcloud/releases/download/${version}/${tarball}"

        json="$(printf '{"download":"%s", "signature": "%s"}' \
            "${downloadUrl}" \
            "$(cat "${signatureFile}")" \
        )"

        readonly json

        "${CURL}" \
            --data "${json}" \
            --header "Content-Type: application/json" \
            --header "Authorization: Token ${nextcloudToken}" \
            --request POST \
            'https://apps.nextcloud.com/api/v1/apps/releases'
    }

    createTag "${version}"
    updateLocalReleaseBranch 'publish'
    installDependencies "${sourceDirectory}"
    createTarball "${sourceDirectory}" "${tarball}"
    updateRemoteReleaseBranch
    createGithubRelease "${version}" "${githubToken}"
    uploadAssetToGithub "${version}" "${githubToken}" "${tarball}"
    createSignature "${tarball}"
    publishToNextcloud "${version}" "${tarball}"
}

if  [ -n "${BASH_SOURCE:-}" ] && [ "${BASH_SOURCE[0]}" != "${0}" ]; then
  export publish
else
  publish "${@}"
  exit $?
fi
