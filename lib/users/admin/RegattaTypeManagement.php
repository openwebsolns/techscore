<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the regatta types and their ranks
 *
 * @author Dayan Paez
 * @created 2013-03-06
 */
class RegattaTypeManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Regatta types", $user);
    $this->page_url = 'types';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->addContent($p = new XPort("Regatta Types"));
    $p->add(new XP(array(), "Use the table below to edit the regatta types available in the program. Order the different regatta types by how they should appear in the public interface, with the most important first. Regatta types in use may not be deleted."));
    $p->add(new XP(array(), "To add a new regatta type, add a title in the row labeled \"New\". To delete a type, check the box in its row. Please note that only types not in use may be deleted."));
    $p->add(new XP(array(), "Check the \"Tweet\" box to specify that a tweet should be sent after a daily summary is entered for regattas of a given type. Note that Twitter integration is contingent on the feature being enabled under \"Social settings\"."));
    $p->add(new XP(array('class'=>'warning'), "Editing a regatta type's title will cause the program to regenerate all affected regattas. Make sure you know what you are doing before proceeding."));
    $p->add($f = $this->createForm());

    // Do not allow re ID of in-use regatta types either
    $f->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'),
                                   array("",
                                         "#",
                                         "Title",
                                         "Tweet",
                                         // "Description",
                                         "Delete")));
    require_once('regatta/Regatta.php');
    $i = 1;
    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $type) {
      $del = "";
      if (count(DB::getAll(DB::$FULL_REGATTA, new DBCond('type', $type))) == 0)
        $del = new XCheckboxInput('delete[]', $type->id, array('title'=>"Check to delete type."));

      $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', $i, array('size'=>2)),
                                                new XHiddenInput('type[]', $type->id))),
                         new XTD(array('class'=>'drag'), ""),
                         new XTextInput('title[]', $type->title, array('maxlength'=>30)),
                         // new XTextArea('description[]', $type->description, array('max'=>250)),
                         $chk = new XCheckboxInput('tweet[]', $type->id),
                         $del),
                   array('class'=>'sortable row'.($i % 2)));

      if ($type->tweet_summary !== null)
        $chk->set('checked', 'checked');

      $i++;
    }
    $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', $i, array('size'=>2)),
                                              new XHiddenInput('type[]', '-new-'))),
                       new XTD(array('class'=>'drag'), "New"),
                       new XTextInput('title[]', "", array('maxlength'=>30)),
                       new XCheckboxInput('tweet[]', '-new-'),
                       ""),
                 array('class'=>'sortable row'.($i % 2)));
    $f->add(new XSubmitP('edit-types', "Edit types"));
  }

  public function process(Array $args) {
    if (isset($args['edit-types'])) {
      // Require map of 'type', and 'title'
      $map = DB::$V->reqMap($args, array('type', 'title'), null, "Invalid list of types.");
      $ord = DB::$V->incList($args, 'order', count($map['type']));
      $twt = DB::$V->incList($args, 'tweet');
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map['type'], $map['title']);

      $del = DB::$V->incList($args, 'delete');

      // Make sure all types are present, including any new ones
      $new_type = null;
      $affected_types = array();
      $deleted_types = array();
      $regen_required = array();
      $rank = 1;
      foreach ($map['type'] as $i => $id) {
        if ($id == '-new-') {
          $title = DB::$V->incString($map['title'], $i, 1, 31);
          if ($title !== null) {
            $new_id = $this->genID($title);
            $type = DB::get(DB::$TYPE, $new_id);
            if ($type === null) {
              $type = new Type();
              $type->id = $new_id;
            }
            $type->inactive = null;
            $type->rank = $rank;
            $type->title = $title;
            $type->tweet_summary = DB::$V->incInt($twt, '-new-', 1, 2, null);
            $new_type = $type;
            $rank++;
          }
        }
        else {
          $type = DB::$V->reqID($map['type'], $i, DB::$ACTIVE_TYPE, "Invalid type provided.");
          // Deletion?
          if (in_array($type->id, $del)) {
            require_once('regatta/Regatta.php');
            if (count(DB::getAll(DB::$FULL_REGATTA, new DBCond('type', $type))) > 0)
              throw new SoterException("Cannot delete types in use.");
            $type->inactive = 1;
            $deleted_types[] = $type;
          }
          else {
            $title = DB::$V->reqString($map['title'], $i, 1, 31,  "Invalid (or empty) title for $id.");

            $tweet = null;
            if (in_array($type->id, $twt))
              $tweet = 1;

            if ($title != $type->title) {
              $type->title = $title;
              $affected_types[] = $type;
              $regen_required[] = $type;
            }
            if ($rank != $type->rank) {
              $type->rank = $rank;
              $affected_types[] = $type;
            }
            if ($tweet != $type->tweet_summary) {
              $type->tweet_summary = $tweet;
              $affected_types[] = $type;
            }
            $rank++;
          }
        }
      }

      if (count($deleted_types) > 0) {
        foreach ($deleted_types as $type)
          DB::set($type);
        Session::pa(new PA(sprintf("Deleted the following type(s): %s.", implode(", ", $deleted_types)), PA::I));
      }
      if (count($affected_types) > 0) {
        $num_regs = 0;
        foreach ($affected_types as $type) {
          DB::set($type);
          if (in_array($type, $regen_required)) {
            // Update all regattas!
            require_once('regatta/Regatta.php');
            require_once('public/UpdateManager.php');
            foreach (DB::getAll(DB::$REGATTA, new DBCond('type', $type)) as $reg) {
              UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_DETAILS);
              $num_regs++;
            }
          }
        }
        Session::pa(new PA(sprintf("Edited %s regatta type(s).", count($affected_types))));
        if ($num_regs > 0)
          Session::pa(new PA(sprintf("%s regatta(s) were queued for re-generation.", $num_regs), PA::I));
      }

      if ($new_type !== null) {
        DB::set($new_type);
        Session::pa(new PA(sprintf("Added new regatta type \"%s\".", $new_type)));
      }
    }
  }

  private function genID($name) {
    $name = strtolower($name);

    // Convert dashes, slashes and underscores into spaces
    $name = str_replace('-', ' ', $name);
    $name = str_replace('/', ' ', $name);
    $name = str_replace('_', ' ', $name);

    // White list permission
    $name = preg_replace('/[^0-9a-z\s_+]+/', '', $name);

    // Trim and squeeze spaces
    $name = trim($name);
    $name = preg_replace('/\s+/', '-', $name);

    return $name;
  }
}
?>