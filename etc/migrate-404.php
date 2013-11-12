<?php
// Migrate the 404 content to the database

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('xml5/Session.php');
require_once('users/admin/TextManagement.php');
$admins = DB::getAdmins();

$a = "*** Page overboard!

We're sorry, but the page you are requesting, or the school you seek, cannot be found. This can happen if:

  -  the URL misspelled the ID of the school,
  -  or the school is not recognized.

Make sure the ID of the school is in upper case, as in *schools/MIT* vs *schools/mit*.

Also make sure the season (if any) is spelled correctly. This should be in lower case and one of f for Fall or s for Spring; followed by the last two digits of the year.

Of course, your best bet is to visit the schools directory to view all the schools in the system.

*Happy sailing!*";

$P = new TextManagement($admins[0], Text_Entry::SCHOOL_404);
$P->process(array('content'=>$a));

$a = "*** Page overboard!

We're sorry, but the page you are looking cannot be found. Thar be two possible reasons for this:

  -  the page never joined the crew on this here vessel, or
  -  it has since walked the plank.

*** How to navigate this site

We try to make our sites easy to navigate. Starting at our home page, you can navigate by following the examples below:

  -  Schools: *schools*
     -   School ID, e.g. *schools/MIT*
        -    Fall 2010 summary *schools/MIT/f10*
  -  Fall 2013: *f13*
     -   Regatta, e.g. Test Hosts *f13/test-hosts*";

$P = new TextManagement($admins[0], Text_Entry::GENERAL_404);
$P->process(array('content'=>$a));
?>