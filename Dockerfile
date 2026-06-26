# For publishing to public registries
FROM public.ecr.aws/docker/library/php:7-apache

RUN apt-get update && apt-get install -y libpng-dev mariadb-client nano
RUN docker-php-ext-install mysqli pcntl gd
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN sed -i 's/;max_input_vars = 1000/max_input_vars = 3000/' "$PHP_INI_DIR/php.ini"
# Let PHP take all the memory in the task
RUN sed -i 's/^memory_limit = 128M/memory_limit = -1/' "$PHP_INI_DIR/php.ini"

COPY src/apache.conf.default-docker /etc/apache2/sites-enabled/techscore.conf
COPY www/index.php /var/www/html/index.php
COPY bin /var/www/bin
COPY lib /var/www/lib
COPY src/db /var/www/src/db

RUN ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/
RUN ln -s /etc/apache2/mods-available/headers.load /etc/apache2/mods-enabled/

# Hack to invoke alternative startup
COPY ./src/techscore-apache2-foreground /usr/local/bin/
COPY ./src/techscore-processor-foreground /usr/local/bin/

CMD ["techscore-apache2-foreground"]
