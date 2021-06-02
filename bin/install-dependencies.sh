#!/usr/bin/env bash

set -e

if [ ! -d extensions/external-db ]; then
    echo 'This script must be run from the repository root.'
    exit 1
fi

for PROG in composer find sed head tail
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
    echo ${1}
}

(
    cd "$REPO_ROOT"

    log_step "Initial clean-up"
    rm -Rf vendor
    rm -Rf third-party
    rm -Rf php-scoper/vendor

    log_step "Installing composer dependencies"
    composer install

    log_step "Removing unnecessary assets from the vendor folder"
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

    log_step "Removing unnecessary parts of the AWS SDK"
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

    log_step "Removing unnecessary parts of the Twilio SDK"
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

    log_step "Running PHP Scoper"
    composer --working-dir=php-scoper install
		php -d memory_limit=256M php-scoper/vendor/bin/php-scoper add-prefix --prefix=WSAL_Vendor --output-dir=./third-party/vendor --force

    composer run autoload-third-party

    ### static files :: start

    # move list of statically loaded files as dump-autoload ignores these
    lineStart="$(grep -n "public static \$files" vendor/composer/autoload_static.php | head -n 1 | cut -d: -f1)"
    lineEnd="$(grep -n "public static \$prefixLengthsPsr4" vendor/composer/autoload_static.php | head -n 1 | cut -d: -f1)"
    lineTarget="$(grep -n "public static \$classMap" third-party/vendor/composer/autoload_static.php | head -n 1 | cut -d: -f1)"

    head -n $((lineTarget-1)) ./third-party/vendor/composer/autoload_static.php > third-party/vendor/composer/autoload_static-tmp.php
    head -n $(($lineEnd-2)) vendor/composer/autoload_static.php | tail -n $lineStart >> third-party/vendor/composer/autoload_static-tmp.php
    tail -n +$lineTarget ./third-party/vendor/composer/autoload_static.php >> third-party/vendor/composer/autoload_static-tmp.php
    mv third-party/vendor/composer/autoload_static-tmp.php third-party/vendor/composer/autoload_static.php

    cat vendor/composer/autoload_files.php | grep -v "aws-sdk-php" | sed "s#    '#    'wsal_#" > third-party/vendor/composer/autoload_files.php

    lineTarget="$(grep -n "return \$loader;" third-party/vendor/composer/autoload_real.php | head -n 1 | cut -d: -f1)"

    head -n $((lineTarget-1)) ./third-party/vendor/composer/autoload_real.php > third-party/vendor/composer/autoload_real-tmp.php
    cat php-scoper/static-files.helper.txt >> third-party/vendor/composer/autoload_real-tmp.php
    tail -n +$lineTarget ./third-party/vendor/composer/autoload_real.php >> third-party/vendor/composer/autoload_real-tmp.php
    mv third-party/vendor/composer/autoload_real-tmp.php third-party/vendor/composer/autoload_real.php
    sed -i'.bak' -e 's/Composer\\\\Autoload/WSAL_Composer\\\\Autoload/' third-party/vendor/composer/*.php && rm -rf third-party/vendor/composer/*.php.bak

    ### static files :: end

    cp -R vendor/freemius third-party
    cp -R vendor/woocommerce third-party

    rm vendor/bin/jp.php

	  rm -Rf vendor/guzzlehttp vendor/freemius vendor/mirazmac vendor/monolog vendor/mtdowling vendor/psr vendor/ralouphie vendor/symfony vendor/twilio vendor/woocommerce

    find ./vendor/aws/aws-sdk-php/ -type f -exec sed -i '' 's#JmesPath\\#WSAL_Vendor\\JmesPath\\#' *.php {} \;
    find ./vendor/aws/aws-sdk-php/ -type f -exec sed -i '' 's#GuzzleHttp\\#WSAL_Vendor\\GuzzleHttp\\#' *.php {} \;
    find ./vendor/aws/aws-sdk-php/ -type f -exec sed -i '' 's#Psr\\#WSAL_Vendor\\Psr\\#' *.php {} \;
    find ./vendor/maxbanton/cwh/ -type f -exec sed -i '' 's#Monolog\\#WSAL_Vendor\\Monolog\\#' *.php {} \;

    composer dump-autoload
)
exit

log_step "Done!"
