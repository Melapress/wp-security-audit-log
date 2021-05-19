#!/usr/bin/env bash

set -e

if [ ! -d extensions/external-db ]; then
    echo 'This script must be run from the repository root.'
    exit 1
fi

for PROG in composer find sed unzip
do
	which ${PROG}
	if [ 0 -ne $? ]
	then
		echo "${PROG} not found in path."
		exit 1
	fi
done

REPO_ROOT=${PWD}

log_step() {
    echo
    echo
    echo ${1}
    echo
    echo
}

log_step "Install the latest v3 of the AWS SDK"
#mkdir sdk
(
    rm -Rf vendor
    composer install

    cd "$REPO_ROOT"/vendor

    find . -type d -iname .github -exec rm -rf {} +
    find . -type f -iname .gitignore -exec rm {} +
    find . -type f -iname LICENSE -exec rm {} +
    find . -type f -iname LICENSE.txt -exec rm {} +
    find . -type f -iname *.md -exec rm {} +
    find . -type f -iname README.* -exec rm {} +
    find . -type f -iname package.json -exec rm {} +
    find . -type f -iname composer.json -exec rm {} +
    find . -type f -iname phpunit.xml* -exec rm {} +
    find . -type f -iname phpstan.* -exec rm {} +
    find . -type f -iname phpdox.* -exec rm {} +
    find . -type f -iname Dockerfile* -exec rm {} +

    # Remove tests & docs
    find . -type d -iname tests -exec rm -rf {} +
    find . -type d -iname docs -exec rm -rf {} +

    cd "$REPO_ROOT"/vendor/aws/aws-sdk-php/src

    # Delete everything from the SDK except for S3, CloudFront and Common files.
    find . -type d -mindepth 1 -maxdepth 1 \
      ! -name CloudWatch \
      ! -name CloudWatchLogs \
      ! -name CloudFront \
      ! -name Api \
      ! -name data \
      ! -name Credentials \
      ! -name Crypto \
      ! -name Endpoint \
      ! -name EndpointDiscovery \
      ! -name Arn \
      ! -name Exception \
      ! -name Retry \
      ! -name Handler \
      ! -name Multipart \
      ! -name Signature \
      ! -name ClientSideMonitoring \
      -exec rm -rf {} +

    cd "$REPO_ROOT"/vendor/twilio/sdk/src/Twilio

    rm -Rf Jwt
    rm -Rf Rest/Accounts*
    rm -Rf Rest/Autopilot*
    rm -Rf Rest/Bulkexports*
    rm -Rf Rest/Conversations*
    rm -Rf Rest/Events*
    rm -Rf Rest/FlexApi*
    rm -Rf Rest/Chat*
    rm -Rf Rest/Fax*
    rm -Rf Rest/Insights*
    rm -Rf Rest/IpMessaging*
    rm -Rf Rest/Lookups*
    rm -Rf Rest/Monitor*
    rm -Rf Rest/Notify*
    rm -Rf Rest/Numbers*
    rm -Rf Rest/Preview*
    rm -Rf Rest/Pricing*
    rm -Rf Rest/Proxy*
    rm -Rf Rest/Serverless*
    rm -Rf Rest/Studio*
    rm -Rf Rest/Supersim*
    rm -Rf Rest/Sync*
    rm -Rf Rest/Taskrouter*
    rm -Rf Rest/Trunking*
    rm -Rf Rest/Trusthub*
    rm -Rf Rest/Verify*
    rm -Rf Rest/Video*
    rm -Rf Rest/Voice*
    rm -Rf Rest/Wireless*
    rm -Rf TaskRouter
    rm -Rf TwiML

    cd "$REPO_ROOT"

    # Remove unused classes from the autoloader's classmap.
    composer dump-autoload
)
exit

log_step "Done!"