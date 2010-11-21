<?php
  // Receives notices from outside world. These notices should have
  // two parameters: a regatta id (reg) and a type (type) such as
  // 'delete|update'.

// IP address that can connect
$whitelist = array("18.251.3.92",
		   "18.181.2.39",
		   "127.0.0.1");
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist))
  exit(1);

include '../../ts-view/main.php';

// Check for total update
if (isset($_REQUEST['season'])) {
  include '../../ts-view/params.php';
  updateSeason($_REQUEST['season']);
  updateFront();
  echo "Updated season.\n";
  exit(0);
}

// Check for reg
if (!isset($_REQUEST['reg']) || !is_numeric($_REQUEST['reg']))
  exit(2);

// Check for type
$types = array("delete", "update");
$type  = "update";
if (isset($_REQUEST['type'])) {
  $type = strtolower($_REQUEST['type']);
  if (!in_array($type, $types)) {
    echo "Invalid operation supplied.";
    exit(4);
  }
}

// OUTPUT
$id = (int)($_REQUEST['reg']);
if ($type == "update") {
  if (updateRegatta($id)) {
    echo "Done!\n";
  }
  else {
    echo "Could not update regatta $id.\n";
    exit(1);
  }
}
elseif ($type == "delete") {
  if (deleteRegatta($id))
    echo "Done!\n";
  else {
    updateAllSeasons();
    updateFront();
    echo "Could not delete regatta $id. Updated front pages.\n";
    exit(16);
  }
}
?>
