PHPUNIT = docker compose -f compose.tests.yaml run --build --remove-orphans techscore-test

default: db

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

# Unit and integration testing
unit-test:
	${PHPUNIT} ./tst/unit

integration-test:
	${PHPUNIT} tst/integration

single-test:
	${PHPUNIT} $(class)

tests: unit-test integration-test
