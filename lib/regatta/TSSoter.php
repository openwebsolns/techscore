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
  public function hasRace(&$value, Array $args, $key, Regatta $reg) {
    try {
      $value = $this->reqRace($args, $key, $reg);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }
  public function reqScoredRace(Array $args, $key, Regatta $reg, $mes = "GSE") {
    $race = $this->reqRace($args, $key, $reg, $mes);
    if (count($reg->getFinishes($race)) == 0)
      throw new SoterException($mes);
    return $race;
  }
  public function incScoredRace(Array $args, $key, Regatta $reg, $default = null) {
    try {
      return $this->reqScoredRace($args, $key, $reg);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
  public function hasScoredRace(&$value, Array $args, $key, Regatta $reg) {
    try {
      $value = $this->reqScoredRace($args, $key, $reg);
      return true;
    }
    catch (SoterException $e) {
      return false;
    }
  }
  public function reqTeam(Array $args, $key, Regatta $reg, $mes = "GSE") {
    $team = $this->reqID($args, $key, DB::$TEAM, $mes);
    if ($team->regatta != $reg)
      throw new SoterException($mes);
    return $team;
  }
  public function incTeam(Array $args, $key, Regatta $reg, Team $default = null) {
    try {
      return $this->reqID($args, $key, $reg);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
  public function reqDivisions(Array $args, $key, Array $pos_values, $min = 0, $mes = "GSE") {
    if (!isset($args[$key]) || !is_array($args[$key]))
      throw new SoterException($mes);
    $list = array();
    foreach ($args[$key] as $div) {
      try {
        $division = Division::get($div);
        if (in_array($division, $pos_values))
          $list[] = $division;
      }
      catch (InvalidArgumentException $e) {}
    }
    if (count($list) < $min)
      throw new SoterException($mes);
    return $list;
  }
  public function incDivisions(Array $args, $key, Array $pos_values, $min = 0, Array $def_values = array()) {
    try {
      return $this->reqDivisions($args, $key, $pos_values, $min);
    }
    catch (SoterException $e) {
      return $def_values;
    }
  }
  public function reqDivision(Array $args, $key, Array $pos_divisions, $mes = "GSE") {
    try {
      $div = Division::get($this->reqString($args, $key, 1, 2, $mes));
      if (!in_array($div, $pos_divisions))
        throw new SoterException($mes);
      return $div;
    }
    catch (InvalidArgumentException $e) {
      throw new SoterException($mes);
    }
  }
  public function incDivision(Array $args, $key, Array $pos_divisions, $default = null) {
    try {
      return $this->reqDivision($args, $key, $pos_divisions);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
  public function reqValues(Array $args, $key, Array $pos_values, $min = 0, $mes = "GSE") {
    $list = array();
    foreach ($this->reqList($args, $key, null, $mes) as $item) {
      if (in_array($item, $pos_values))
        $list[] = $item;
    }
    if (count($list) < $min)
      throw new SoterException($mes);
    return $list;
  }
  public function incValues(Array $args, $key, Array $pos_values, $min = 0, Array $default = array()) {
    try {
      return $this->reqValues($args, $key, $pos_values, $min);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
  public function reqSchool(Array $args, $key, $mes = "GSE") {
    $sch = DB::getSchool($this->reqString($args, $key, 1, 11, $mes));
    if ($sch === null)
      throw new SoterException($mes);
    return $sch;
  }
  public function incSchool(Array $args, $key, $default = null) {
    try {
      return $this->reqSchool($args, $key);
    }
    catch (SoterException $e) {
      return $default;
    }
  }
}
?>