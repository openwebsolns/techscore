include Makefile.local

default: apache.conf changes.current.sql crontab

crontab: crontab.default
	touch crontab; cp crontab crontab.orig; sed 's:{DIRECTORY}:'"`pwd`"':g' crontab.default > crontab

apache.conf: apache.conf.default
	sed -e 's:{DIRECTORY}:'"`pwd`"':g' -e 's/{DIRECTORY}/'"$(hostname)"'/g' apache.conf.default > apache.conf

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
	phpdoc --ignore conf.php --target doc --title "TechScore Documentation" --directory lib

css:
	mkdir -p html/inc/css;\
	cat res/inc/css/modern-public.css | tr "\n" " " | tr "\t" " " | tr -s " " | sed -e 's:/\*[^\*]*\*/::g' -e 's/: \+/:/g' -e 's/; \+/;/g' -e 's/ *{ */{/g' -e 's/ *} */}/g' -e 's/^ *//' > html/inc/css/mp.css

js:
	mkdir -p html/inc/js; cp res/inc/js/report.js html/inc/js
