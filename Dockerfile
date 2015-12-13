FROM php:5.4

ENV DEBIAN_FRONTEND=noninteractive LC_ALL=C DOCKER=1

RUN apt-get update

RUN apt-get -qq install -qq -y php5-cli php-pear

RUN apt-get -qq install -qq -y git-core

RUN apt-get -qq install -qq -y libcurl4-openssl-dev

RUN apt-get -qq install -qq -y libicu-dev

RUN docker-php-ext-install curl

RUN docker-php-ext-install intl

RUN docker-php-ext-install mbstring

RUN docker-php-ext-install mysql

RUN apt-get -qq install -qq -y php5-redis && pecl install -o -f redis && \

    rm -rf /tmp/pear && \

    echo "extension=redis.so" > /usr/local/etc/php/conf.d/redis.ini

RUN apt-get -qq install -qq -y php5-xdebug && pecl install -o -f xdebug && \

    rm -rf /tmp/pear && \

    echo "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20100525/xdebug.so" > /usr/local/etc/php/conf.d/xdebug.ini

RUN \

    curl -sS https://phar.phpunit.de/phpunit-old.phar > phpunit.phar && \

    curl -sS https://getcomposer.org/installer | php && \

    mv composer.phar /usr/local/bin/composer && \

    mv phpunit.phar /usr/local/bin/phpunit && \

    chmod +x /usr/local/bin/composer /usr/local/bin/phpunit && \

    phpunit --version

ENV DB=mysql db_dsn='mysql://travis@0.0.0.0/cakephp_test'

ADD composer.json /app/composer.json

WORKDIR /app

RUN echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini

RUN composer self-update

RUN composer install --prefer-source --no-interaction --dev

RUN sh -c "if [ '$DB' = 'mysql' ]; then if [ '$DOCKER' = '1' ]; then apt-get -qq install -qq -y mysql-server && service mysql start; fi; mysql -e 'CREATE DATABASE cakephp_test;'; fi"

RUN sh -c "if [ '$DB' = 'pgsql' ]; then psql -c 'CREATE DATABASE cakephp_test;' -U postgres; fi"

RUN sh -c "if [ '$PHPCS' = '1' ]; then composer require 'cakephp/cakephp-codesniffer:dev-master'; fi"

RUN sh -c "if [ '$COVERALLS' = '1' ]; then composer require --dev satooshi/php-coveralls:dev-master; fi"

RUN sh -c "if [ '$COVERALLS' = '1' ]; then mkdir -p build/logs; fi"

RUN command -v phpenv > /dev/null && phpenv rehash || true

ADD . /app

ENV COVERALLS=1 DEFAULT=1 PHPCS=1

RUN sh -c "if [ '$COVERALLS' = '1' ]; then phpunit --stderr --coverage-clover build/logs/clover.xml; fi"

RUN sh -c "if [ '$COVERALLS' = '1' ]; then php vendor/bin/coveralls -c .coveralls.yml -v; fi"

RUN sh -c "if [ '$DEFAULT' = '1' ]; then phpunit --stderr; fi"

RUN sh -c "if [ '$PHPCS' = '1' ]; then vendor/bin/phpcs -n -p --extensions=php --standard=vendor/cakephp/cakephp-codesniffer/CakePHP --ignore=vendor --ignore=docs --ignore=tests/bootstrap.php . ; fi"

