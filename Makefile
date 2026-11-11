LIBSRC := $(shell find lib -name "*.php")
PHPSERVER = php -S localhost:8080 -t www tst/integration/router.php
EC2_SERVER= php -S localhost:8081 tst/integration/ec2-instance-metadata-router.php
COVERAGE_DIR = etc/coverage
COVERAGE_TEMP_DIR = /tmp
PHPUNIT = phpunit --stderr --bootstrap tst/conf.php --testdox

default: css-admin js-admin db

.PHONY:	doc db

db:
	@php bin/cli.php MigrateDB

doc:
	rm -r doc/* && \
	docker run --rm -v ${PWD}:/data phpdoc/phpdoc:3 --ignore conf.*php \
	  --target doc \
	  --title "TechScore Documentation" \
	  --directory lib \
	  --defaultpackagename regatta

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
