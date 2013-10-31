<?php
/**
 * Load the sponsors into the database
 *
 * @author Dayan Paez
 * @created 2013-10-31
 */

require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('users/admin/SponsorsManagement.php');
require_once('xml5/Session.php');

$admins = DB::getAdmins();
if (count($admins) == 0)
  die("There is no one to do the migration\n");

$P = new SponsorsManagement($admins[0]);
$sponsors = array(array('http://gillna.com', 'gill.png', "Gill"),
                  array('http://www.apsltd.com', 'aps.png', "APS"),
                  array('http://www.sperrytopsider.com/', 'sperry-gray.png', "Sperry Top-Sider"),
                  array('http://www.laserperformance.com/', 'laserperformance.png', "LaserPerformance"),
                  array('http://www.marlowropes.com', 'marlow.png', "Marlow"),
                  array('http://www.ussailing.org/', 'ussailing.png', "US Sailing"),
                  array('http://www.quantumsails.com/', 'qtag.png', "Quantum Sails"));
foreach ($sponsors as $sponsor) {
  $args = array('name' => $sponsor[2],
                'url'  => $sponsor[0],
                'logo' => $sponsor[1],
                'add'  => 'Add');
  $P->process($args);
}
?>