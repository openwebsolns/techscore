# Techscore by OpenWeb Solutions, LLC

Techscore is a web-based, sailing regatta scoring and management platform. It runs on
Object-Oriented PHP as part of a standard LAMP stack. For information about running Techscore on
AWS, see the [techscore-aws](https://github.com/openwebsolns/techscore-aws) project.


## Apologia

Techscore was first openly published in 2018, ten years after the project began as simply a
web-based UI to scoring regattas. It replaced a DOS-based, survey driven application known only as
"Navy Scoring". The web was different in 2008. JavaScript support was very fragmented across
different browsers and the few PHP frameworks that existed focused on speed over security and
maintainability. As a result, Techscore introduced and has since created different conventions and
is best approached with a fresh pair of eyes. All the same, experienced developers will soon find
obvious implementation analogies to common design principles.


## Grand tour

The key to getting started developing for Techscore is to get a sense of the directory structure.

### Nomenclature

* `repo root`: The root of the repository; i.e. the directory containing this README.md
  file. Without any other qualifier, `root` refers to this directory. May be called `global root`.
  
### Directory: www

Everything that is directly served to the user's browser by the HTTP server (usually: Apache) is
located in this directory. This includes every statically generated CSS, JavaScript, image file;
favicon; and, notably, exactly one PHP file: `index.php`. This file is the entry point to the
application *from the web*. It is the script loaded by Apache for every route or webpage visited
that is not one of the aforementioned static files. (This is done via Apache rewrite rules; covered
under a separate guide.) This directory is sometimes called the `web root`.

Most of Techscore resides outside this directory, and the `index.php` file serves as an adapter:
handling all the HTTP and URL-related logic, before delegating to the application underneath.

### Directory: lib

This is the most important directory: `lib` is supposed to stand for "library". It is where all the
logic is tucked away. It resides explicitly outside the web root so that there is never a
possibility of direct access via URL [1](#fn-lib).



## Footnotes

<a name="fn-lib">1</a>: By contrast, most other PHP applications are "drop-in" by nature: unzip a
directory tree and drop it into the "web root" of your web server, usually a third-party
website. For example, in WordPress, you can directly access PHP files that are internal to the
application by going to a URL matching the filesystem path of the file: `/wp-includes/foo.php`.
