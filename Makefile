include Makefile.local

.PHONY:	sql, doc
sql:
	mysqldump $(DB_DB) -u $(DB_USER) -p --compact --no-data | \
	sed 's/CREATE TABLE/CREATE TABLE IF NOT EXISTS/ig' > db.sql

doc:
	phpdoc --ignore conf.php --target doc --title "TechScore Documentation" --directory lib