# Techscore Library

This directory houses the brain of the application. Most files are PHP classes, one per file,
namespaced according to their file structure. As Techscore matured from PHP4 through PHP7, some
language features and design principles have been retroactively applied, although not always fully.

More detailed description about each directory can be found in specific README.md files. Below is
some general information.

## conf.php

This is the entry point of the application, and the first file to be loaded whether from the browser
(see `www/index.php`) or from a script (see `bin/cli.php`). Its job is to bootstrap the running
context, CLI or otherwise, to provide the following features:

  * setup autoloading: provides dynamic loading of a namespaced class by looking at the
    directory structure in this directory. I.e. `use \foo\bar\Clazz` will load the script at
    `<repo-root>/lib/foo/bar/Clazz.php` when first used.
	
  * toggle some PHP INI settings. (These used to live in the global `/etc/php.ini`, but that made
    migrating of the application more difficult.)
	
  * setup error handler: when things go wrong, controls what the end-user sees on their browser.
  
  * **setup database connection**
  
  * initialize the user (and the session): depending on execution (CLI vs. web), this could be the
    logged-in user as retrieved from a web session, or the "root" user for the CLI. Many parts of
    the application customize their behavior based on the user.
	

## conf.default.php and conf.local.php

While most application-level settings are stored in the database, there are a few low-level
parameters that need to be available before the database connection is even established. To
customize such settings, `conf.php` will load a file in this directory named `conf.local.php`. While
technically a PHP file, it contains mostly variable assignments, where the variables are defined as
static properties of the `Conf` object. A basic template is provided in
[`conf.default.php`](lib/conf.default.php). Any installation first begins by copying then modifying
`conf.local.php` with the specific settings.


## Debug.php (optional)

For quick troubleshooting, you may opt to write a file with the name `Debug.php` in this directory,
whose first line should be:

```php
require_once('conf.php');
```

This file is not tracked by the repository and can therefore serve as a quick playground. For actual
testing, consider adding unit and integration tests under `<repo_root>/tst`.


## Directory: model

Contains all the ORM-like classes used in the project. Each of these classes map to a database
object.  Techscore uses a custom object-relational mapping library, two versions of which can be
found under the `mysqli` and `MyORM` directories.


## Directories: tscore and users

The `tscore` directory contains all the regatta-scoring "panes". The term pane was borrowed from
Java Spring's nomenclature early on, and in this project describes the controllers in a typical MVC
architecture. Anything related to actually scoring a regatta is stored in this directory.

All other application panes can be found under `users`, a section of Techscore that has grown to be
larger than the scoring portion itself and now comprises:

  * Management of general user/school settings
  * Report generation: `users/reports`
  * Administrator access: `users/admin`
  * Sailor registration management: `users/membership`


## Directory: regatta

Used to contain more model files, but now houses mostly utility classes related to scoring a
regatta.
