<?php
namespace ui;

use \Breakdown;
use \InvalidArgumentException;
use \Penalty;

use \XDiv;
use \XQuickTable;
use \XSelect;
use \XSpan;
use \XTable;
use \XTH;
use \XTHead;
use \XTBody;
use \XTD;
use \XTR;
use \XTextInput;

/**
 * A UI component to enter finishes in one race.
 *
 * | Place | Sail | Type |          | Sail |
 * |-------+------+------|          |------|
 * | 1st   | [__] |      | <spacer> | 1    |
 * | 2nd   | [__] |      |          | 2    |
 * | 3rd   | [__] |      |          | 3    |
 * | 4th   | [__] |      |          | 4    |
 *
 * The label (e.g. Sail) is specified at creation time, along with the
 * possible values to be used in each of the rows.
 *
 * @author Dayan Paez
 * @version 2015-11-02
 */
class EnterFinishesWidget extends XDiv {

  const CLASSNAME = 'finishes-widget';
  const OPTIONS_CLASSNAME = 'finishes-widget-options';
  const OPTIONS_ENTRY_CLASSNAME = 'finishes-widget-option';
  const FINISHES_CLASSNAME = 'finishes-widget-finishes';
  const FINISHES_CHECK_TD_CLASSNAME = 'finishes-widget-check';
  const FINISHES_PLACE_TD_CLASSNAME = 'finishes-widget-place-td';
  const FINISHES_TYPE_TD_CLASSNAME = 'finishes-widget-type-td';
  const FINISHES_PLACE_CLASSNAME = 'finishes-widget-place small no-mselect';
  const FINISHES_TYPE_CLASSNAME = 'finishes-widget-type no-mselect';
  const SPACER_CLASSNAME = 'finishes-widget-spacer';

  const MAX_ROW_COUNT = 6;
  const MAX_COL_COUNT = 3;

  /**
   * @var String name of what we're using to enter finishes (i.e. Sail or Team)
   */
  private $label;
  /**
   * @var Array associative array of options.
   */
  private $options;
  /**
   * @var boolean true to use dropdowns for choosing place finish.
   */
  private $useDropdowns;

  private $optionsTable;
  private $finishesTable;
  private $finishesRows;
  private $finishIndex;

  /**
   * Auto-injected list of finish type options.
   */
  private $typeOptions;

  /**
   * @var int Calculated number of columns to arrange cells in.
   */
  private $numberOfColumns;
  /**
   * @var int Calculated number of rows.
   */
  private $numberOfRows;

  /**
   * Create a new widget creator.
   *
   * @param String $label such as "Sails" or "Teams".
   * @param Array $options the map of possible key-value pairs.
   */
  public function __construct($label, Array $options) {
    parent::__construct(array('class' => self::CLASSNAME));

    $this->label = (string) $label;
    $this->options = $options;
    $this->setUseDropdowns(true);

    $this->numberOfColumns = $this->calculateNumberOfColumns();
    $this->numberOfRows = $this->calculateNumberOfRows();

    $this->createOptionsTable();
    $this->createFinishesTable();
    $this->add($this->finishesTable);
    $this->add(new XSpan("", array('class' => self::SPACER_CLASSNAME)));
    $this->add($this->optionsTable);
    $this->finishIndex = 0;
  }

  /**
   * Adds the next entry to the place finishes, with given options.
   *
   * @param String $option the chosen value (could be null).
   * @param String $finishType the chosen finish type (null).
   * @throws InvalidArgumentException if already full.
   */
  public function addPlace($option, $finishType) {
    if ($this->finishIndex >= count($this->options)) {
      throw new InvalidArgumentException("Number of places exceeds number of options.");
    }

    $columnIndex = floor($this->finishIndex / $this->numberOfRows);
    $color = ($columnIndex % 2 == 0) ? ' even' : ' odd';
    $row = $this->finishesRows[$this->finishIndex % $this->numberOfRows];
    $row->add(
      new XTD(
        array('class' => self::FINISHES_CHECK_TD_CLASSNAME . $color),
        $this->getOrdinalValue($this->finishIndex + 1)
      )
    );

    $row->add(
      new XTD(
        array('class' => self::FINISHES_PLACE_TD_CLASSNAME . $color),
        $this->getPlaceWidget($this->finishIndex, $option)
      )
    );

    $typeName = sprintf('finishes[%d][modifier]', $this->finishIndex);
    $row->add(
      new XTD(
        array('class' => self::FINISHES_TYPE_TD_CLASSNAME . $color),
        XSelect::fromArray(
          $typeName,
          $this->getFinishTypeOptions(),
          $finishType,
          array('class' => self::FINISHES_TYPE_CLASSNAME)
        )
      )
    );

    $this->finishIndex++;
  }

  public function getColumnsCount() {
    return $this->numberOfColumns;
  }

  public function getRowsCount() {
    return $this->numberOfRows;
  }

  private function createOptionsTable() {
    $this->optionsTable = new XTable(
      array('class' => self::OPTIONS_CLASSNAME),
      array(
        new XTHead(
          array(),
          array(
            new XTR(
              array(),
              array(
                new XTH(
                  array('colspan' => $this->numberOfColumns),
                  $this->label
                )
              )
            )
          )
        ),
        $body = new XTBody()
      )
    );

    $optionKeys = array_keys($this->options);
    for ($row = 0; $row < $this->numberOfRows; $row++) {
      $body->add($tr = new XTR());

      for ($col = 0; $col < $this->numberOfColumns; $col++) {
        $i = $row + ($col * $this->numberOfRows);
        if ($i < count($optionKeys)) {
          $key = $optionKeys[$i];
          $tr->add(
            new XTD(
              array(
                'class' => self::OPTIONS_ENTRY_CLASSNAME,
                'data-value' => $key
              ),
              $this->options[$key]
            )
          );
        }
      }
    }
  }

  private function createFinishesTable() {
    $this->finishesTable = new XTable(
      array('class' => self::FINISHES_CLASSNAME),
      array(
        new XTHead(array(), array($tr = new XTR())),
        $body = new XTBody()
      )
    );

    for ($i = 0; $i < $this->numberOfColumns; $i++) {
      $tr->add(new XTH(array(), "Place"));
      $tr->add(new XTH(array(), $this->label));
      $tr->add(new XTH(array(), "Type"));
    }

    $this->finishesRows = array();
    for ($i = 0; $i < $this->numberOfRows; $i++) {
      $row = new XTR();
      $body->add($row);
      $this->finishesRows[] = $row;
    }
  }

  /**
   * Based on size of options: how many columns should each table have?
   *
   * @return int the number of columns.
   */
  private function calculateNumberOfColumns() {
    $maxLabelSize = 0;
    foreach ($this->options as $label) {
      $size = mb_strlen($label);
      if ($size > $maxLabelSize) {
        $maxLabelSize = $size;
      }
    }
    if ($maxLabelSize > 6) {
      return 1;
    }

    $columnsBasedOnRows = ceil(count($this->options) / self::MAX_ROW_COUNT);
    return min($columnsBasedOnRows, self::MAX_COL_COUNT);
  }

  /**
   * Calculate the number of rows for an even-looking experience.
   *
   * Attempt to create a fairly square table.
   */
  private function calculateNumberOfRows() {
    $numberOfColumns = $this->numberOfColumns;
    $numberOfCells = count($this->options);
    $numberOfRows = self::MAX_ROW_COUNT;

    // Too few columns
    if ($numberOfColumns <= 2) {
      return ceil($numberOfCells / $numberOfColumns);
    }

    // Too many cells
    $totalCellsThatFit = $numberOfRows * $numberOfColumns;
    if ($totalCellsThatFit <= $numberOfCells) {
      return ceil($numberOfCells / $numberOfColumns);
    }

    $lastColumnCount = $numberOfCells % $numberOfRows;
    $columnsBasedOnRows = ceil($numberOfCells / $numberOfRows);
    while (
      $lastColumnCount > 0
      && $lastColumnCount <= $numberOfRows / 2
      && $columnsBasedOnRows <= $numberOfColumns
    ) {
      $numberOfRows--;
      $lastColumnCount = $numberOfCells % $numberOfRows;
      $columnsBasedOnRows = ceil($numberOfCells / $numberOfRows);
    }
    return $numberOfRows;
  }

  private function getPlaceWidget($i, $chosen) {
    $inputName = sprintf('finishes[%d][entry]', $i);
    if ($this->useDropdowns) {
      $options = array("" => "");
      foreach ($this->options as $key => $value) {
        $options[$key] = $value;
      }
      return XSelect::fromArray(
        $inputName,
        $options,
        $chosen,
        array(
          'class' => self::FINISHES_PLACE_CLASSNAME,
          'tabindex' => ($i + 1),
          'required' => 'required'
        )
      );
    }
    return new XTextInput(
      'p' . $i,
      $chosen,
      array(
        'tabindex' => ($i + 1),
        'class' => self::FINISHES_PLACE_CLASSNAME,
        'required' => 'required',
        'size' => '2'
      )
    );
  }

  private function getOrdinalValue($i) {
    switch ($i) {
    case 1:
      return "1st";
    case 2:
      return "2nd";
    case 3:
      return "3rd";
    default:
      return $i . "th";
    }
  }

  public function setUseDropdowns($flag) {
    $this->useDropdowns = ($flag !== false);
  }

  public function setFinishTypeOptions(Array $options) {
    $this->typeOptions = $options;
  }

  private function getFinishTypeOptions() {
    if ($this->typeOptions == null) {
      $this->typeOptions = array(
        "" => "",
        Penalty::DNF => Penalty::DNF,
        Penalty::DNS => Penalty::DNS,
        Breakdown::BYE => Breakdown::BYE
      );
    }
    return $this->typeOptions;
  }

}