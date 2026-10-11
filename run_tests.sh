#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

#######################
#### Tests with non temporary sniffers
#######################
php -d apc.enable_cli=1 ./vendor/bin/phpunit

#######################
#### Tests with temporary sniffers
#### Skip MySQL
#######################
if [ $DRIVER != 'Mysql' ]; then
  export SNIFFERS_IN_TEMP_MODE="true"
  php -d apc.enable_cli=1 ./vendor/bin/phpunit
fi
