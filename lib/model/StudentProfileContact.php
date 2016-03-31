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

}