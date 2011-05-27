include Makefile.local

default: apache.conf changes.current.sql crontab

crontab: crontab.default Makefile.local
	sed -e 's:{DIRECTORY}:'"`pwd`"':g' \
	    -e 's:{CRON_MAILTO}:${CRON_MAILTO}:g' \
	    -e 's:{CRON_DLY_FREQ}:${CRON_DLY_FREQ}:g' \
	    -e 's:{CRON_WKD_FREQ}:${CRON_WKD_FREQ}:g' crontab.default > crontab

apache.conf: apache.conf.default Makefile.local
	sed -e 's:{DIRECTORY}:'"`pwd`"':g' \
	    -e 's/{HOSTNAME}/'"`hostname`"'/g' \
	    -e 's:{HTTP_LOGROOT}:${HTTP_LOGROOT}:g' \
	    -e 's:{HTTP_CERTPATH}:${HTTP_CERTPATH}:g' \
		apache.conf.default > apache.conf

changes.current.sql: changes.history.sql
	touch changes.current.sql && \
	comm -13 changes.current.sql changes.history.sql | mysql -u $(DB_USER) -p $(DB_DB) && \
	cp changes.history.sql changes.current.sql

.PHONY:	sql doc
sql:
	echo "SET FOREIGN_KEY_CHECKS=0;" \
		`mysqldump $(DB_DB) -u $(DB_USER) -p --compact --no-data | \
		sed 's/CREATE TABLE/CREATE TABLE IF NOT EXISTS/ig'` \
		"SET FOREIGN_KEY_CHECKS=1;" > db.sql

doc:
	phpdoc --ignore conf.php --target doc --title "TechScore Documentation" --directory lib --output "HTML:Smarty:PHP"

css:
	mkdir -p html/inc/css;\
	cat res/inc/css/modern-public.css | tr "\n" " " | tr "\t" " " | tr -s " " | sed -e 's:/\*[^\*]*\*/::g' -e 's/: \+/:/g' -e 's/; \+/;/g' -e 's/ *{ */{/g' -e 's/ *} */}'"\n"'/g' -e 's/^ *//' > html/inc/css/mp.css;\
	cat res/inc/css/mp-front.css | tr "\n" " " | tr "\t" " " | tr -s " " | sed -e 's:/\*[^\*]*\*/::g' -e 's/: \+/:/g' -e 's/; \+/;/g' -e 's/ *{ */{/g' -e 's/ *} */}'"\n"'/g' -e 's/^ *//' > html/inc/css/mp-front.css

js:
	mkdir -p html/inc/js; cp res/inc/js/report.js html/inc/js
