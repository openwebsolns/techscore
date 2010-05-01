#!/usr/bin/php
<?php
  // Command line script to notify subscribers of a given regatta. In
  // particular, it notifies the public version of TechScore, located
  // at ts2.xvm.mit.edu (soon to be regatta.mit.edu). It does this by
  // requesting the "__notice" page from each subscriber.
  //
  // This script is meant to be run as a background process so as not
  // to hang up the parent script. It takes as input the 'id' of the
  // regatta to notify about and the type of notification
  // (i.e. update, delete)

  // Usage
function usage() {
  echo "Usage: report.cli.php regattaID <update|delete>\n";
  exit(1);
}
if (count($argv) < 3)
  usage();

$id = $argv[1];
$type = $argv[2];

// Notify
$addr = array('http://ts2.xvm.mit.edu/__notice.php',
	      'http://paez.mit.edu/view/__notice.php');
foreach ($addr as $a) {
  if ($f = file(sprintf("%s?reg=%s&type=%s", $a, $id, $type)))
    foreach ($f as $l)
      echo $l;
  else {
    echo "Unable to notify subscribers.\n";
  }
}
?>