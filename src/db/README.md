# Database Schema Migrations

This directory tracks the schema and changes to that schema for the
database of the project.

Meanwhile, the database stores, under the meta table _schema_ the set
of files that have been imported. Those are the files with names:

  * `NNNNN_description_of_change.sql`

where NNNNN is a padded sequential number. This allows for serial
tracking of changes. The script MigrateDB.php will compare all the NNNNN
files in the directory with those in the database, and source, in
order, the ones that are missing in order to upgrade the database.

"Upgrades" are stored in the up directory. These are obviously
required.  It is strongly encouraged that you include a matching
downgrade file in 'down'.
