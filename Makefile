LIBSRC := $(shell find lib -name "*.php")
PHPSERVER = php -S localhost:8080 -t www tst/integration/router.php
EC2_SERVER= php -S localhost:8081 tst/integration/ec2-instance-metadata-router.php
COVERAGE_DIR = etc/coverage
COVERAGE_TEMP_DIR = /tmp
PHPUNIT = phpunit --stderr --bootstrap tst/conf.php --testdox

default: lib/conf.local.php src/apache.conf src/changes.current.sql src/crontab css-admin js-admin src/md5sum db

lib/conf.local.php: lib/conf.default.php
	@echo "Manually create lib/conf.local.php from lib/conf.default.php" && exit 1

src/crontab: src/crontab.default bin/Make.php lib/conf.local.php
	php bin/Make.php crontab && \
	echo -e "\nPlease reinstall src/crontab!\n"

src/apache.conf: src/apache.conf.default bin/Make.php lib/conf.local.php
	php bin/Make.php apache.conf

src/changes.current.sql: src/changes.history.sql
	@if [ -f src/changes.current.sql ]; then \
	  comm -13 src/changes.current.sql src/changes.history.sql | \
	  mysql -v -u $$(php bin/Make.php getprop DB_ROOT_USER) -p $$(php bin/Make.php getprop SQL_DB) && \
	  rm src/changes.current.sql; \
	fi

src/db/schema.sql: src/db/up/*.sql src/db/down/*.sql
	mysqldump -u $$(php bin/Make.php getprop DB_ROOT_USER) -p $$(php bin/Make.php getprop SQL_DB) \
	  --no-data --skip-comments | \
	sed 's/ AUTO_INCREMENT=[0-9]*\b//' > src/db/schema.sql

src/md5sum: $(LIBSRC) bin/Make.php
	php bin/Make.php md5sum

.PHONY:	doc school-404 db schema

db:
	@php bin/cli.php MigrateDB

schema: src/db/schema.sql

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


# Unit and integration testing
unit-test:
	${PHPUNIT} tst/unit

single-unit-test:
	${PHPUNIT} --include-path tst/unit $(class)

integration-test:
	${PHPSERVER} & \
	PID=$$!; \
	${EC2_SERVER} & \
	EC2_PID=$$!; \
	echo "Servers started in PIDs: $$PID/$$EC2_PID"; \
	${PHPUNIT} tst/integration; \
	kill $$PID $$EC2_PID

single-integration-test:
	${PHPSERVER} & \
	PID=$$!; \
	${EC2_SERVER} & \
	EC2_PID=$$!; \
	echo "Servers started in PIDs: $$PID/$$EC2_PID"; \
	${PHPUNIT} --include-path tst/integration $(class); \
	kill $$PID $$EC2_PID

tests: unit-test integration-test

coverage:
	mkdir -p ${COVERAGE_DIR}; \
	${PHPUNIT} --coverage-html ${COVERAGE_DIR} tst/unit

single-coverage:
	${PHPUNIT} --coverage-html ${COVERAGE_TEMP_DIR} --include-path tst/unit $(class)

server:
	${PHPSERVER}
