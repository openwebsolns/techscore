<?php
namespace users\membership;

use \ui\AddSailorsTable;
use \users\AbstractUserPane;
use \xml5\GraduationYearInput;
use \xml5\PageWhiz;
use \xml5\SailorPageWhizCreator;
use \xml5\XExternalA;

use \Account;
use \DB;
use \Conf;
use \Permission;
use \RegisteredSailor;
use \Sailor;
use \Session;
use \SoterException;
use \STN;

use \XA;
use \XCollapsiblePort;
use \XPort;
use \XQuickTable;
use \XSpan;
use \XSubmitP;

/**
 * Manages the database of sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-12
 */
class SailorsPane extends AbstractUserPane {

  const NUM_PER_PAGE = 30;
  const INPUT_DOWNLOAD = 'download';
  const DOWNLOAD_CSV = 'csv';
  const SEARCH_KEY = 'q';

  const SUBMIT_ADD = 'add-sailor';

  const PORT_ADD = "Add sailor";
  const PORT_LIST = "All sailors";

  const FIELD_SAILORS = 'sailor';

  public function __construct(Account $user) {
    parent::__construct("Sailors", $user);
  }

  public function fillHTML(Array $args) {
    if ($this->USER->can(Permission::ADD_SAILOR)) {
      $this->fillAddNew($args);
    }
    $this->fillList($args);
  }

  private function fillAddNew(Array $args) {
    $this->PAGE->addContent($p = new XCollapsiblePort(self::PORT_ADD));
    $p->add($form = $this->createForm());
    $form->add(new AddSailorsTable(self::FIELD_SAILORS, $this->USER->getSchools()));
    $form->add(new XSubmitP(self::SUBMIT_ADD, "Add"));
  }

  private function fillList(Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_LIST));
    $link = $this->link();

    $whizCreator = new SailorPageWhizCreator($this->USER, $args, self::NUM_PER_PAGE, $link);
    $sailors = $whizCreator->getMatchedSailors();
    if (array_key_exists(self::INPUT_DOWNLOAD, $args)) {
      $this->downloadSailors($sailors);
      return;
    }

    $whiz = $whizCreator->getPageWhiz();
    $slice = $whiz->getSlice($sailors);
    $ldiv = $whiz->getPageLinks();

    $p->add($whizCreator->getFilterForm($link));
    $p->add($whizCreator->getSearchForm());
    $p->add($ldiv);
    if (count($slice) > 0) {
      $downloadArgs = $args;
      $downloadArgs[self::INPUT_DOWNLOAD] = self::DOWNLOAD_CSV;
      $ldiv->add(new XSpan(new XA($this->link($downloadArgs), "Download", array('target'=>'_blank')), array('class' => 'download-link')));

      $p->add($this->getSailorsTable($slice));
    }
    $p->add($ldiv);
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_ADD, $args)) {
      if (!$this->USER->can(Permission::ADD_SAILOR)) {
        throw new SoterException("No permission to add sailor.");
      }
      $sailorList = DB::$V->reqList($args, self::FIELD_SAILORS, null, "No list of sailors provided.");
      $genders = Sailor::getGenders();
      $sailors = array();
      foreach ($sailorList as $sailObject) {
        $sailor = new RegisteredSailor();
        $sailor->school = DB::$V->incSchool($sailObject, 'school');
        if (
          $sailor->school !== null
          && !$this->USER->hasSchool($sailor->school)
        ) {
          throw new SoterException("Invalid school provided.");
        }
        $sailor->first_name = DB::$V->incString($sailObject, 'first_name');
        $sailor->last_name = DB::$V->incString($sailObject, 'last_name');
        $sailor->year = DB::$V->incInt($sailObject, 'year', GraduationYearInput::MINIMUM);
        $sailor->gender = DB::$V->incKey($sailObject, 'gender', $genders);

        if (
          $sailor->school !== null
          && $sailor->first_name !== null
          && $sailor->last_name !== null
          && $sailor->year !== null
          && $sailor->gender !== null
        ) {
          if (DB::g(STN::SAILOR_PROFILES)) {
            $sailor->url = DB::createUrlSlug(
              $sailor->getUrlSeeds(),
              function ($slug) use ($sailor) {
                $other = DB::getSailorByUrl($slug);
                return ($other === null || $other->id == $sailor->id);
              }
            );
          }
          $sailors[] = $sailor;
        }
      }

      $count = count($sailors);
      if ($count == 0) {
        throw new SoterException("No sailors provided.");
      }

      foreach ($sailors as $sailor) {
        DB::set($sailor);
      }
      if ($count == 1) {
        Session::info(sprintf("Added %s.", $sailors[0]));
      }
      else {
        Session::info(sprintf("Added %d sailors.", $count));
      }
    }
  }

  private function getSailorsTable($sailors) {
    $genders = Sailor::getGenders();
    $useProfiles = DB::g(STN::SAILOR_PROFILES) !== null;
    $headers = array(
      "ID",
      "Full Name",
      "School",
      "Year",
      "Gender",
    );
    if ($useProfiles) {
      $headers[] = "URL";
    }
    $table = new XQuickTable(
      array('class' => 'sailors-table'),
      $headers
    );

    foreach ($sailors as $i => $sailor) {
      $id = $sailor->id;
      if ($this->canEdit($sailor)) {
        $id = new XA(
          $this->linkTo('users\membership\SingleSailorPane', array(SingleSailorPane::EDIT_KEY => $id)),
          $id
        );
      }

      $row = array(
        $id,
        $sailor,
        $sailor->school,
        $sailor->year,
        $genders[$sailor->gender],
      );
      if ($useProfiles) {
        $url = '';
        if ($useProfiles && $sailor->url !== null) {
          $url = new XExternalA(
            sprintf('http://%s%s', Conf::$PUB_HOME, $sailor->getURL()),
            $sailor->url
          );
        }

        $row[] = $url;
      }
        
      $table->addRow($row, array('class' => 'row' . ($i % 2))
      );
    }

    return $table;
  }

  private function canEdit(Sailor $sailor) {
    return (
      $this->USER->hasSchool($sailor->school)
      && (
        $this->USER->can(Permission::EDIT_SAILOR_LIST)
        || $this->USER->can(Permission::EDIT_UNREGISTERED_SAILORS)
      )
    );
  }

  private function downloadSailors($sailors) {
    header('Content-type: application/octet-stream');
    header('Content-Disposition: attachment; filename=techscore-sailors.tsv');

    printf("%s\n", implode("\t", array('id', 'first_name', 'last_name', 'school_id', 'school', 'year', 'gender', 'url')));
    foreach ($sailors as $sailor) {
      $fields = array(
        $sailor->id,
        $sailor->first_name,
        $sailor->last_name,
        $sailor->school->id,
        $sailor->school->name,
        $sailor->year,
        $sailor->gender,
        $sailor->url,
      );
      printf("%s\n", implode("\t", $fields));
    }
    exit;
  }
}
