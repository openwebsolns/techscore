<?php
namespace model;

use \DB;
use \DBEnum;

/**
 * Contact information for a given student profile.
 *
 * The last one added for a given student profile is considered "the
 * latest and bestest".
 *
 * @author Dayan Paez
 * @version 2016-03-31
 */
class StudentProfileContact extends AbstractObject {

  const CONTACT_TYPE_HOME = 'home';
  const CONTACT_TYPE_SCHOOL = 'school';

  protected $student_profile;
  protected $contact_type;
  public $email;
  public $address_1;
  public $address_2;
  public $city;
  public $state;
  public $postal_code;
  public $telephone;
  public $secondary_telephone;
  protected $current_until;

  public function db_name() {
    return 'student_profile_contact';
  }

  public function db_type($field) {
    switch ($field) {
    case 'contact_type':
      return new DBEnum(array(self::CONTACT_TYPE_HOME, self::CONTACT_TYPE_SCHOOL));
    case 'current_until':
      return DB::T(DB::NOW);
    case 'student_profile':
      return DB::T(DB::STUDENT_PROFILE);
    default:
      return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('created_on' => false);
  }

  public static function createFromArgs(Array $args, $errorFormatString = '%s') {
    $contact = new StudentProfileContact();
    $contact->email = DB::$V->reqEmail($args, 'email', sprintf($errorFormatString, "Missing or invalid e-mail address."));
    $contact->address_1 = DB::$V->reqString($args, 'address_1', 1, 16000, sprintf($errorFormatString, "Missing line 1 of the address."));
    $contact->address_2 = DB::$V->incString($args, 'address_1');
    $contact->city = DB::$V->reqString($args, 'city', 1, 16000, sprintf($errorFormatString, "Missing city."));
    $contact->state = DB::$V->reqString($args, 'state', 2, 3, sprintf($errorFormatString, "Invalid state provided."));
    $contact->postal_code = DB::$V->reqString($args, 'postal_code', 1, 16, sprintf($errorFormatString, "Invalid postal code provided."));
    $contact->telephone = DB::$V->reqString($args, 'telephone', 1, 21, sprintf($errorFormatString, "Invalid telephone provided."));
    $contact->secondary_telephone = DB::$V->incString($args, 'secondary_telephone', 1, 21);
    $contact->current_until = DB::$V->incDate($args, 'current_until');
    return $contact;
  }

}