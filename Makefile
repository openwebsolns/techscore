LIBSRC := $(shell find lib -name "*.php")

default: lib/conf.local.php src/apache.conf src/changes.current.sql src/crontab css-admin js-admin src/md5sum db

lib/conf.local.php: lib/conf.default.php
	@echo "Manually create lib/conf.local.php from lib/conf.default.php" && exit 1

src/crontab: src/crontab.default bin/Make.php lib/conf.local.php
	php bin/Make.php crontab && \
	echo -e "\nPlease reinstall src/crontab!\n"

src/apache.conf: src/apache.conf.default bin/Make.php lib/conf.local.php
	php bin/Make.php apache.conf

src/changes.current.sql: src/changes.history.sql
	touch src/changes.current.sql && \
	comm -13 src/changes.current.sql src/changes.history.sql | \
	mysql -v -u $$(php bin/Make.php getprop DB_ROOT_USER) -p $$(php bin/Make.php getprop SQL_DB) && \
	cp src/changes.history.sql src/changes.current.sql

src/md5sum: $(LIBSRC) bin/Make.php
	php bin/Make.php md5sum

.PHONY:	doc school-404 db

db:
	php lib/scripts/MigrateDB.php

school-404: lib/scripts/Update404.php
	php lib/scripts/Update404.php schools

doc:
	rm -r doc/* && \
	phpdoc --ignore conf.*php \
	  --target doc \
	  --title "TechScore Documentation" \
	  --directory lib \
	  --defaultpackagename regatta \
	  --output "HTML:Smarty:PHP"

# CSS goodness

www/inc/css/%.css: res/www/inc/css/%.css
	mkdir -pv www/inc/css && \
	tr "\n" " " < $^ | \
	tr -s " " | \
	sed -e 's:/\*[^(\*/)]*\*/::g' -e 's/\(;\|:\|}\|{\)[ 	]*/\1/g' \
	    -e 's/[ 	]*{/{/g'      -e 's/^[ 	]*//' > $@

css-admin:   $(subst res/www,www,$(wildcard res/www/inc/css/*.css))

# Javascript goodness

www/inc/js/%.js: res/www/inc/js/%.js
	mkdir -pv www/inc/js && \
	minijs.sh < $^ > $@ || cp $^ $@

js-admin: $(subst res/www,www,$(wildcard res/www/inc/js/*.js))
