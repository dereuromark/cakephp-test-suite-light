#!/bin/bash

DRIVER=$1;

echo "Starting PHPUNIT tests"
export DB_DRIVER=$DRIVER

#######################
#### Tests with non temporary sniffers
#######################
php ./vendor/bin/phpunit

#######################
#### Tests with temporary sniffers
#### Skip MySQL
#######################
if [ $DRIVER != 'Mysql' ]; then
  export SNIFFERS_IN_TEMP_MODE="true"
  php ./vendor/bin/phpunit
fi
