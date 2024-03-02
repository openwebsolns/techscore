# For publishing to public registries
FROM public.ecr.aws/docker/library/php:7-apache

COPY src/apache.conf.default-docker /etc/apache2/sites-enabled/techscore.conf
COPY res/www /var/www/html
COPY www /var/www/html
COPY bin /var/www/bin
COPY lib /var/www/lib
COPY src/db /var/www/src/db

RUN ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/
RUN ln -s /etc/apache2/mods-available/headers.load /etc/apache2/mods-enabled/

RUN docker-php-ext-install mysqli pcntl
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Hack to invoke alternative startup
COPY ./src/techscore-apache2-foreground /usr/local/bin/
COPY ./src/techscore-processor-foreground /usr/local/bin/

ENV AWS_CONTAINER_CREDENTIALS_RELATIVE_URI="MISSING"

CMD ["techscore-apache2-foreground"]
