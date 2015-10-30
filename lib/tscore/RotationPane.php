<?php
namespace tscore;

use \Account;
use \ByeTeam;
use \DB;
use \Division;
use \Regatta;
use \SailsList;
use \Session;
use \SoterException;
use \UpdateManager;
use \UpdateRequest;
use \WS;

use \AbstractPane;
use \FReqItem;
use \XA;
use \XForm;
use \XHiddenInput;
use \XNumberInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XRawText;
use \XScript;
use \XSelect;
use \XSubmitInput;
use \XSubmitP;
use \XTD;

use \model\FleetRotation;
use \rotation\FleetRotationCreatorSelector;
use \rotation\descriptors\AggregatedRotationDescriptor;
use \rotation\descriptors\RotationDescriptor;
use \rotation\validators\AggregatedFleetRotationValidator;
use \rotation\validators\FleetRotationValidator;
use \tscore\utils\FleetRotationParser;
use \ui\ProgressDiv;
use \ui\SailsTable;
use \ui\SortableTable;

/**
 * Pane to create the rotations.
 *
 * @author Dayan Paez
 * @version 2015-10-20
 */
class RotationPane extends AbstractPane {

  const STEP_1 = 'settings';
  const STEP_2 = 'sails';

  // Options for rotation types
  private $ROTS = array(
    FleetRotation::TYPE_STANDARD => "Standard: +1 each set",
    FleetRotation::TYPE_SWAP => "Swap:  Odds up, evens down",
    FleetRotation::TYPE_NONE => "Same boat throughout"
  );
  private $STYLES = array(
    FleetRotation::STYLE_NAVY => "Navy: rotate on division change",
    FleetRotation::STYLE_FRANNY => "Franny: automatic offset",
    FleetRotation::STYLE_SIMILAR => "All divisions similar"
  );

  private $rotationParser;
  private $rotationCreatorSelector;
  private $rotationValidator;
  private $rotationDescriptor;

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Setup rotations", $user, $reg);
    $this->rotationParser = new FleetRotationParser($reg);
  }

  /**
   * Fills the HTML body, accounting for combined divisions, etc
   *
   */
  protected function fillHTML(Array $args) {
    $manager = $this->REGATTA->getRotationManager();

    $rotation = null;
    try {
      $rotation = $this->rotationParser->fromArgs($args);
      $this->prepareForStep2($rotation);
      if (DB::$V->incInt($args, 'step', 1) !== 1
          && $rotation->rotation_type != null
          && $rotation->rotation_style != null
          && $rotation->division_order != null
          && $rotation->races_per_set != null) {

        $this->fillStep2($rotation, $args);
        return;
      }
    }
    catch (SoterException $e) {
      Session::error($e->getMessage());
    }

    if (count($args) == 0) {
      $rotation = $manager->getFleetRotation();
    }
    if ($rotation == null) {
      $rotation = new FleetRotation();
    }

    // Default
    $this->fillStep1($rotation, $args);
    $this->fillFaq($args);
  }

  /**
   * Step 1: Prompt for rotation type.
   *
   * Choose a rotation type: SWAP rotations are allowed due to the
   * presence of a possible BYE team. Because of this, even for
   * combined scoring, the user must be given the choice of rotation
   * to use FIRST, which is this step here.
   *
   * @param Array $args the GET request.
   */
  private function fillStep1(FleetRotation $rotation, Array $args) {
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/rotation-inputs.js'), null, array('async'=>'async', 'defer'=>'defer')));

    $this->addProgressDiv(1);
    $this->PAGE->addContent($p = new XPort("Create a rotation"));
    $p->add($form = $this->createForm(XForm::GET));
    $form->set('id', 'sail_setup');
    $form->add(new XP(array(), "\"Swap divisions\" require an even number of total teams at the time of creation. If you choose swap division, a \"BYE Team\" will be added as needed to make the total number of teams even. This will produce an unused boat in every race."));

    $form->add(
      new FReqItem(
        "Rotation type:",
        XSelect::fromArray('rotation_type', $this->ROTS, $rotation->rotation_type, array('id'=>'input_rotation_type')),
        "Swap divisions require an even number of total teams at the time of creation. If you choose swap division, a \"BYE Team\" will be added as needed to make the total number of teams even. This will produce an unused boat in every race."
      )
    );
    if ($this->REGATTA->getEffectiveDivisionCount() > 1) {
      $form->add(
        new FReqItem(
          "Division switch style:",
          XSelect::fromArray('rotation_style', $this->STYLES, $rotation->rotation_style, array('id'=>'input_rotation_style')),
          "Dictates how the rotation type is applied between divisions."
        )
      );

      // Division order
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/TableSorter.js'), null, array('async'=>'async', 'defer'=>'defer')));

      $form->add(
        new FReqItem(
          "Division order:",
          $tab = new SortableTable(array("#", "Division"), true),
          "Specifies the order the races will be sailed; and how the rotation will be set."
        )
      );
      $tab->set('id', 'input_division_order');

      $division_order = $rotation->division_order;
      if ($division_order == null) {
        $division_order = array();
        foreach ($this->REGATTA->getDivisions() as $division) {
          $division_order[] = (string) $division;
        }
      }
      foreach ($division_order as $div) {
        $tab->addSortableRow('division_order', $div, array($div));
      }
    }

    // Races per set
    $races_per_set = 2;
    if ($rotation->races_per_set != null) {
      $races_per_set = $rotation->races_per_set;
    }
    $form->add(
      new FReqItem(
        "Races in set:",
        new XNumberInput('races_per_set', $races_per_set, 1, null, 1, array('size'=>'2', 'id'=>'input_races_per_set'))
      )
    );

    $form->add(new XSubmitP("choose_rot", "Next →"));
  }

  /**
   * Step 2: starting sails.
   *
   */
  private function fillStep2(FleetRotation $rotation, Array $args) {
    $descriptor = $this->getRotationDescriptor();

    $this->addProgressDiv(2);
    $this->PAGE->addContent($p = new XPort("Enter sail numbers"));
    $p->add($form = $this->createForm());
    $form->set('id', 'rotation-form');
    $form->add(new XP(array(), $descriptor->describe($rotation)));

    // Sails
    $divisions = $this->REGATTA->getDivisions();
    $teamNames = array();
    foreach ($this->REGATTA->getTeams() as $team) {
      $teamNames[] = (string) $team;
    }
    if ($rotation->rotation_type == FleetRotation::TYPE_SWAP && count($teamNames) % 2 > 0) {
      $teamNames[] = (string) new ByeTeam();
    }
    $rotationDivisions = array(Division::A());
    if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
      $rotationDivisions = $divisions;
    }

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/rot.js')));
    $table = new SailsTable($rotation);
    $form->add(new FReqItem("Enter sails in first race:", $table));

    // Submit form
    $form->add(new XSubmitP('create-rotation', "Create rotation"));

    $args = $this->rotationParser->toArgs($rotation);
    unset($args['order']);
    unset($args['sails']);
    unset($args['colors']);
    foreach ($args as $key => $values) {
      if (is_array($values)) {
        $key .= '[]';
      }
      else {
        $values = array($values);
      }
      foreach ($values as $value) {
        $form->add(new XHiddenInput($key, $value));
      }
    }
  }

  /**
   * Insert the FAQ section.
   *
   * @param Array $args ignored.
   */
  private function fillFaq(Array $args) {
    if ($this->REGATTA->getEffectiveDivisionCount() > 1) {
      // FAQ's
      $this->PAGE->addContent($p = new XPort("FAQ"));
      $fname = sprintf("%s/faq/sail.html", dirname(__FILE__));
      $p->add(new XRawText(file_get_contents($fname)));
    }
  }

  /**
   * Sets up rotations according to requests. The request for creating
   * a new rotation should include:
   * <dl>
   *   <dt>
   *
   * </dl>
   */
  public function process(Array $args) {
    $rotation = $this->rotationParser->fromArgs($args);
    $rotation->regatta = $this->REGATTA;
    $validator = $this->getFleetRotationValidator();
    $validator->validateFleetRotation($rotation);
    $rotationCreator = $this->getFleetRotationCreator($rotation);
    $rotationCreator->createRotation($rotation);
    DB::set($rotation);

    UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
    Session::info(
      array(
        "New rotation successfully created. ",
        new XA(
          sprintf('/view/%s/rotation', $this->REGATTA->id),
          "View",
          array('onclick'=> 'javascript:this.target="rotation";')
        ),
        "."
      )
    );
    $this->redirect('finishes');
  }

  private function addProgressDiv($step) {
    $steps = array(
      "Settings",
      "Starting Sails"
    );
    $this->PAGE->addContent($prog = new ProgressDiv());
    for ($i = 1; $i < $step + 1; $i++) {
      $prog->addStage(
        $steps[$i - 1],
        $this->link('rotations', array('step' => $i)),
        ($i == $step),
        true
      );
    }
    for ($i = $step + 1; $i < count($steps) + 1; $i++) {
      $prog->addStage($steps[$i - 1]);
    }
  }

  /**
   * Helper function to fill out a rotation object for use in step 2.
   *
   * @param FleetRotation $rotation the rotation object to prepare.
   */
  private function prepareForStep2(FleetRotation $rotation) {
    $rotation->regatta = $this->REGATTA;
    if ($this->REGATTA->getEffectiveDivisionCount() == 1) {
      // no division switch necessary
      $rotation->rotation_style = FleetRotation::STYLE_SIMILAR;
      $rotation->division_order = $this->REGATTA->getDivisions();
    }
    if ($rotation->rotation_type == FleetRotation::TYPE_NONE) {
      $rotation->races_per_set = 1;
    }
    if ($rotation->rotation_type != null && $rotation->rotation_style != null) {
      if ($rotation->sails_list == null) {
        $count = $this->getMinimumSailsCount($rotation);
        $sails = array();
        for ($i = 0; $i < $count; $i++) {
          $sails[] = ($i + 1);
        }
        $rotation->sails_list = new SailsList();
        $rotation->sails_list->sails = $sails;
      }
    }
  }

  private function getMinimumSailsCount(FleetRotation $rotation) {
    $minimumSailCount = count($this->REGATTA->getTeams());
    if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
      $minimumSailCount *= count($this->REGATTA->getDivisions());
    }
    if ($rotation->rotation_type == FleetRotation::TYPE_SWAP) {
      if ($minimumSailCount % 2 != 0) {
        $minimumSailCount++;
      }
    }
    return $minimumSailCount;
  }

  private function getFleetRotationValidator() {
    if ($this->rotationValidator == null) {
      $this->rotationValidator = new AggregatedFleetRotationValidator();
    }
    return $this->rotationValidator;
  }

  public function setFleetRotationValidator(FleetRotationValidator $validator) {
    $this->rotationValidator = $validator;
  }

  private function getRotationDescriptor() {
    if ($this->rotationDescriptor == null) {
      $this->rotationDescriptor = new AggregatedRotationDescriptor();
    }
    return $this->rotationDescriptor;
  }

  public function setRotationDescriptor(RotationDescriptor $descriptor) {
    $this->rotationDescriptor = $descriptor;
  }

  private function getFleetRotationCreator(FleetRotation $rotation) {
    if ($this->rotationCreatorSelector == null) {
      $this->rotationCreatorSelector = new FleetRotationCreatorSelector();
    }
    return $this->rotationCreatorSelector->selectRotationCreator($rotation);
  }

  public function setFleetRotationCreator(FleetRotationCreatorSelector $selector) {
    $this->rotationCreatorSelector = $selector;
  }
}
