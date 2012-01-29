<?php
/*
 * This file is part of TechScore
 *
 */

require_once('mysqli/Soter.php');

/**
 * Special extension of Soter for the purposes of TechScore goodness;
 * providing methods specific to serializing races, etc.
 *
 * @author Dayan Paez
 * @version 2012-01-19
 */
class TSSoter extends Soter {
  final public function hasInt(&$value, Array $args, $key, $min = 0, $max = PHP_INT_MAX) {
    try {
      $value = $this->reqInt($args, $key, $min);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasFloat(&$value, Array $args, $key, $min = 0, $max = PHP_INT_MAX) {
    try {
      $value = $this->reqFloat($args, $key, $min, $max);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasKey(&$value, Array $args, $key, Array $values) {
    try {
      $value = $this->reqKey($args, $key, $values);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasValue(&$value, Array $args, $key, Array $values) {
    try {
      $value = $this->reqValue($args, $key, $values);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasString(&$value, Array $args, $key, $min = 0, $max = 8388608) {
    try {
      $value = $this->reqString($args, $key, $min, $max);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasID(&$value, Array $args, $key, DBObject $obj) {
    try {
      $value = $this->reqID($args, $key, $obj);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasList(&$value, Array $args, $key, $size = null) {
    try {
      $value = $this->reqList($args, $key, $size);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasMap(&$value, Array $args, Array $keys, $size = null) {
    try {
      $value = $this->reqMap($args, $keys, $size);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasDate(&$value, Array $args, $key, DateTime $min = null, DateTime $max = null) {
    try {
      $value = $this->reqDate($args, $key, $min, $max);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasFile(&$value, Array $args, $key, $min = 0, $max = 8388608) {
    try {
      $value = $this->reqFile($args, $key, $min, $max);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  final public function hasRE(&$value, Array $args, $key, $regex) {
    try {
      $value = $this->reqRE($args, $key, $regex);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }

  public function reqRace(Array $args, $key, Regatta $reg, $mes = "GSE") {
    if (!isset($args[$key]))
      throw new SoterException($mes);
    try {
      $race = Race::parse($args[$key]);
      $race = $reg->getRace($race->division, $race->number);
      if ($race === null)
	throw new SoterException($mes);
      return $race;
    }
    catch (Exception $e) {
      throw new SoterException($mes);
    }
  }
  public function incRace(Array $args, $key, Regatta $reg, $default = null) {
    try {
      return $this->reqRace($args, $key, $reg);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
}
?>