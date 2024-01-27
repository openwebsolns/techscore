FROM public.ecr.aws/docker/library/php:7-apache

COPY www/ /var/www/html/
COPY lib/ /var/www/lib/
COPY bin/ /var/www/bin/
COPY src/apache.conf.default-docker /etc/apache2/sites-enabled/techscore.conf

RUN ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/
RUN ln -s /etc/apache2/mods-available/headers.load /etc/apache2/mods-enabled/

RUN docker-php-ext-install mysqli
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# To update regatta
# docker run --network techscore-network --rm --name techscore-update techscore php /var/www/bin/cli.php Daemon -f -vvv regatta