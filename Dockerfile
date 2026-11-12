# Contains PHP2Shell backfixes
FROM public.ecr.aws/docker/library/php:8.4.23-apache

RUN apt-get update && apt-get install -y libpng-dev mariadb-client nano
RUN docker-php-ext-install mysqli pcntl gd

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# Later directives override earlier ones, so add overrides at the end
RUN cat <<EOF >> "$PHP_INI_DIR/php.ini"
max_input_vars = 3000
# Let PHP take all the memory in the task
memory_limit = -1
# Do not expose PHP
expose_php = Off
include_path = ".:/var/www/lib"
date.timezone = "America/New_York"
# Session data
session.name = "Techscore"
session.cookie_httponly = On
session.cookie_secure = On
session.cookie_path = "/"
session.cookie_domain = ""
session.cookie_samesite = "Strict"
session.cookie_lifetime = 0
EOF

# Install AWS CLI for backups and auditing; from https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html
RUN apt-get install -y unzip
RUN curl -fsSL https://awscli.amazonaws.com/v2/install.sh | bash -s -- --system --skip-signature-verification

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
