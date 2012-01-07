include Makefile.local

default: apache.conf changes.current.sql crontab cache/404-schools.html cache/schools.db css css-admin js js-admin

crontab: crontab.default Makefile.local
	sed -e 's:{DIRECTORY}:'"`pwd`"':g' \
	    -e 's:{CRON_MAILTO}:${CRON_MAILTO}:g' \
	    -e 's:{CRON_DLY_FREQ}:${CRON_DLY_FREQ}:g' \
	    -e 's:{CRON_WKD_FREQ}:${CRON_WKD_FREQ}:g' \
	    -e 's:{CRON_BUP_TIME}:${CRON_BUP_TIME}:g' \
	    -e 's:{CRON_BUP_USER}:${CRON_BUP_USER}:g' \
	    -e 's:{CRON_BUP_RECIP}:${CRON_BUP_RECIP}:g' \
		crontab.default > crontab && \
	crontab crontab && echo "Crontab installed"

apache.conf: apache.conf.default Makefile.local
	sed -e 's:{DIRECTORY}:'"`pwd`"':g' \
	    -e 's/{HOSTNAME}/${HTTP_HOSTNAME}/g' \
	    -e 's:{HTTP_LOGROOT}:${HTTP_LOGROOT}:g' \
	    -e 's:{HTTP_CERTPATH}:${HTTP_CERTPATH}:g' \
	    -e 's:{HTTP_CERTKEYPATH}:${HTTP_CERTKEYPATH}:g' \
		apache.conf.default > apache.conf

changes.current.sql: changes.history.sql
	touch changes.current.sql && \
	comm -13 changes.current.sql changes.history.sql | mysql -u $(DB_USER) -p $(DB_DB) && \
	cp changes.history.sql changes.current.sql

cache/404-schools.html: lib/scripts/Update404.php
	php lib/scripts/Update404.php schools

cache/schools.db: lib/xcache/GenerateSchools.php html/schools/404.php
	php lib/xcache/GenerateSchools.php

.PHONY:	sql doc
sql:
	echo "SET FOREIGN_KEY_CHECKS=0;" \
		`mysqldump $(DB_DB) -u $(DB_USER) -p --compact --no-data | \
		sed 's/CREATE TABLE/CREATE TABLE IF NOT EXISTS/ig'` \
		"SET FOREIGN_KEY_CHECKS=1;" > db.sql

doc:
	rm -r doc/* && \
	phpdoc --ignore conf.*php \
	  --target doc \
	  --title "TechScore Documentation" \
	  --directory lib \
	  --defaultpackagename regatta \
	  --output "HTML:Smarty:PHP"

# CSS goodness
# css-admin: www/inc/css/aa.css www/inc/css/cal.css www/inc/css/mobile.css www/inc/css/modern.css www/inc/css/print.css www/inc/css/modern-dialog.css www/inc/css/widescreen.css

html/inc/css/%.css: res/html/inc/css/%.css
	mkdir -pv html/inc/css && \
	tr "\n" " " < $^ | \
	tr -s " " | \
	sed -e 's:/\*[^(\*/)]*\*/::g' -e 's/\(;\|:\|}\|{\)[ 	]*/\1/g' \
	    -e 's/[ 	]*{/{/g'      -e 's/^[ 	]*//' > $@

css:   $(subst res/html,html,$(wildcard res/html/inc/css/%.css))

www/inc/css/%.css: res/www/inc/css/%.css
	mkdir -pv www/inc/css && \
	tr "\n" " " < $^ | \
	tr -s " " | \
	sed -e 's:/\*[^(\*/)]*\*/::g' -e 's/\(;\|:\|}\|{\)[ 	]*/\1/g' \
	    -e 's/[ 	]*{/{/g'      -e 's/^[ 	]*//' > $@

css-admin:   $(subst res/www,www,$(wildcard res/www/inc/css/%.css))

# Javascript goodness

html/inc/js/%.js: res/html/inc/js/%.js
	mkdir -p html/inc/js && \
	minijs.sh < $^ > $@ || cp $^ $@

js:	$(subst res/html,html,$(wildcard res/html/inc/js/*.js))

www/inc/js/%.js: res/www/inc/js/%.js
	mkdir -pv www/inc/js && \
	minijs.sh < $^ > $@ || cp $^ $@

js-admin: $(subst res/www,www,$(wildcard res/www/inc/js/*.js))