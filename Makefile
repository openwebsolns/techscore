include Makefile.local

default: apache.conf changes.current.sql crontab

crontab: crontab.default
	sed 's:{DIRECTORY}:'"`pwd`"':g' crontab.default > crontab

apache.conf: apache.conf.default
	sed 's:{DIRECTORY}:'"`pwd`"':g' apache.conf.default > apache.conf

changes.current.sql: changes.history.sql
	touch changes.current.sql && \
	comm -12 changes.current.sql changes.history.sql | mysql -u $(DB_USER) -p $(DB_DB) && \
	cp changes.history.sql changes.current.sql

.PHONY:	sql doc
sql:
	echo "SET FOREIGN_KEY_CHECKS=0;" \
		`mysqldump $(DB_DB) -u $(DB_USER) -p --compact --no-data | \
		sed 's/CREATE TABLE/CREATE TABLE IF NOT EXISTS/ig'` \
		"SET FOREIGN_KEY_CHECKS=1;" > db.sql

doc:
	phpdoc --ignore conf.php --target doc --title "TechScore Documentation" --directory lib