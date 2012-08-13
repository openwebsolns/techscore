<?php
/**
 * Rehashes all the passwords in the database by prepending to each
 * record, the user's ID, null byte, current (sha1) hash, null byte,
 * and the Conf::$PASSWORD_SALT, which must exist and not be null.
 *
 * This is a one-time use script.
 */
require_once(dirname(__DIR__). '/lib/conf.php');
require_once('regatta/Account.php');

if (strlen(Conf::$PASSWORD_SALT) == 0)
  throw new RuntimeException("PASSWORD_SALT MUST NOT BE EMPTY!");

$updated = 0;
$skipped = 0;
foreach (DB::getAll(DB::$ACCOUNT) as $acc) {
  if (strlen($acc->password) == 128) {
    $skipped++;
    continue;
  }
  $acc->password = hash('sha512', $acc->id . "\0" . $acc->password . "\0" . Conf::$PASSWORD_SALT);
  DB::set($acc);
  $updated++;
}

echo "Updated $updated account(s).\n";
if (count($skipped) > 0)
  echo "Skipped $skipped account(s).\n";
?>