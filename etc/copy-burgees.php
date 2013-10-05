<?php
/**
 * Copy existing burgees to create 40x40 size
 *
 * @author Dayan Paez
 * @created 2013-10-04 
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('regatta/Regatta.php');
require_once('prefs/EditLogoPane.php');
require_once('xml5/Session.php');
$user = DB::getAccount('paez@mit.edu');

$file = '/tmp/burgee.png';
$_FILES['logo_file'] = array('name'=>'', 'tmp_name'=>$file, 'error'=>0, 'size'=>10);
foreach (DB::getAll(DB::$SCHOOL, new DBCond('burgee', null, DBCond::NE)) as $school) {
  echo $school->id;
  $P = new EditLogoPane($user, $school);
  file_put_contents($file, base64_decode($school->burgee->filedata));
  $P->process(array());
  echo "\n";
}
?>