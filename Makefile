include Makefile.local

.PHONY:	sql, doc
sql:
	echo "SET FOREIGN_KEY_CHECKS=0;" \
		`mysqldump $(DB_DB) -u $(DB_USER) -p --compact --no-data | \
		sed 's/CREATE TABLE/CREATE TABLE IF NOT EXISTS/ig'` \
		"SET FOREIGN_KEY_CHECKS=1;" > db.sql

doc:
	phpdoc --ignore conf.php --target doc --title "TechScore Documentation" --directory lib