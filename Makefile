LIBSRC := $(shell find lib -name "*.php")

default: lib/conf.local.php src/apache.conf src/changes.current.sql src/crontab html/schools/404.html css css-admin js js-admin src/md5sum

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

html/schools/404.html: lib/scripts/Update404.php
	mkdir -pv html/schools && php lib/scripts/Update404.php schools

.PHONY:	doc
doc:
	rm -r doc/* && \
	phpdoc --ignore conf.*php \
	  --target doc \
	  --title "TechScore Documentation" \
	  --directory lib \
	  --defaultpackagename regatta \
	  --output "HTML:Smarty:PHP"

# CSS goodness

html/inc/css/%.css: res/html/inc/css/%.css
	mkdir -pv html/inc/css && \
	tr "\n" " " < $^ | \
	tr -s " " | \
	sed -e 's:/\*[^(\*/)]*\*/::g' -e 's/\(;\|:\|}\|{\)[ 	]*/\1/g' \
	    -e 's/[ 	]*{/{/g'      -e 's/^[ 	]*//' > $@

css:   $(subst res/html,html,$(wildcard res/html/inc/css/*.css))

www/inc/css/%.css: res/www/inc/css/%.css
	mkdir -pv www/inc/css && \
	tr "\n" " " < $^ | \
	tr -s " " | \
	sed -e 's:/\*[^(\*/)]*\*/::g' -e 's/\(;\|:\|}\|{\)[ 	]*/\1/g' \
	    -e 's/[ 	]*{/{/g'      -e 's/^[ 	]*//' > $@

css-admin:   $(subst res/www,www,$(wildcard res/www/inc/css/*.css))

# Javascript goodness

html/inc/js/%.js: res/html/inc/js/%.js
	mkdir -p html/inc/js && \
	minijs.sh < $^ > $@ || cp $^ $@

js:	$(subst res/html,html,$(wildcard res/html/inc/js/*.js))

www/inc/js/%.js: res/www/inc/js/%.js
	mkdir -pv www/inc/js && \
	minijs.sh < $^ > $@ || cp $^ $@

js-admin: $(subst res/www,www,$(wildcard res/www/inc/js/*.js))
