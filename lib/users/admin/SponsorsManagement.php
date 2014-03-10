<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the list of sponsors to be used on the public site.
 *
 * @author Dayan Paez
 * @version thedate
 */
class SponsorsManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Sponsors list", $user);
    $this->page_url = 'sponsors';
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Edit existing?
    // ------------------------------------------------------------
    if (isset($args['r'])) {
      try {
        $sponsor = DB::$V->reqID($args, 'r', DB::$PUB_SPONSOR, "Invalid sponsor requested.");
        $this->PAGE->addContent($p = new XPort("Edit " . $sponsor->name));
        $p->add($f = $this->createForm());
        $this->fillForm($f, $sponsor);
        $f->add($xp = new XSubmitP('edit', "Edit"));
        $xp->add(" ");
        $xp->add(new XA(WS::link('/' . $this->page_url), "Cancel"));
        $xp->add(new XHiddenInput('sponsor', $sponsor->id));

        $this->PAGE->addContent($p = new XPort("Delete " . $sponsor->name));
        $p->add(new XP(array(), sprintf("To delete \"%s\" as a sponsor, click the button below. Note that previously generated files will not be affected, only files generated after the deletion.", $sponsor->name)));
        $p->add($f = $this->createForm());
        $f->add($xp = new XSubmitP('delete', "Delete", array(), true));
        $xp->add(new XHiddenInput('sponsor', $sponsor->id));
        return;
      } catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
        WS::go('/' . $this->page_url);
      }
    }

    // ------------------------------------------------------------
    // Current list
    // ------------------------------------------------------------
    $curr = DB::getAll(DB::$PUB_SPONSOR);
    if (count($curr) > 0) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current list"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Edit the order of the sponsors by specifying the order in the first cell, or (if available) dragging the rows around. Click \"Edit order\" to save changes. To delete a sponsor, click \"Edit\" in the last column."));
      $f->add($tab = new XQuickTable(array('class'=>'sponsors-list', 'id'=>'divtable'),
                                     array("Order", "#", "Name", "URL", "Logo", "")));
      foreach ($curr as $i => $sponsor) {
        $url = "";
        if ($sponsor->url !== null)
          $url = new XA($sponsor->url, $sponsor->url, array('target'=>'_blank'));
        $log = "";
        if ($sponsor->logo !== null) {
          $file = $sponsor->logo->getFile();
          $log = new XImg(sprintf('data:%s;base64,%s', $file->filetype, base64_encode($file->filedata)), "");
        }
        $tab->addRow(array(new XTD(array(),
                                   array(new XNumberInput('order[]', ($i + 1), 1, count($curr), 1, array('size'=>2)),
                                         new XHiddenInput('sponsor[]', $sponsor->id))),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           $sponsor->name,
                           $url,
                           $log,
                           new XA(WS::link('/' . $this->page_url, array('r'=>$sponsor->id)), "Edit")),
                     array('class'=>'sortable row'.($i % 2)));
      }
      $f->add(new XSubmitP('reorder', "Edit order"));
    }

    // ------------------------------------------------------------
    // Add new one
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add sponsor"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(),
                   array("Each sponsor should have a name, an optional URL to link to, and an optional logo. The logo to be used must be one of the public files. Visit ",
                         new XA(WS::link('/files'), "Public files"),
                         " to upload a logo first.")));
    $this->fillForm($f, new Pub_Sponsor());
    $f->add(new XSubmitP('add', "Add sponsor"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add a new one
    // ------------------------------------------------------------
    if (isset($args['add'])) {
      $sponsor = new Pub_Sponsor();
      $sponsor->name = DB::$V->reqString($args, 'name', 1, 51, "No name provided for new sponsor.");
      $sponsor->url = DB::$V->incString($args, 'url', 1, 256);
      $sponsor->logo = DB::$V->incID($args, 'logo', DB::$PUB_FILE_SUMMARY);
      if ($sponsor->logo !== null && substr($sponsor->logo->filetype, 0, 6) != 'image/')
        throw new SoterException("Only images may be used for the sponsor logo.");
      $sponsor->relative_order = count(DB::getAll(DB::$PUB_SPONSOR)) + 1;
      DB::set($sponsor);
      Session::pa(new PA(sprintf("Added \"%s\" as new sponsor.", $sponsor->name)));
    }

    // ------------------------------------------------------------
    // Edit order
    // ------------------------------------------------------------
    if (isset($args['reorder'])) {
      $map = DB::$V->reqList($args, 'sponsor', null, "Missing list of sponsors.");
      $ord = DB::$V->incList($args, 'order', count($map));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map);

      $num = 1;
      $changed = array();
      foreach ($map as $i => $id) {
        $sponsor = DB::$V->reqID($map, $i, DB::$PUB_SPONSOR, "Invalid sponsor provided: " . $id);
        $sponsor->relative_order = $num++;
        $changed[] = $sponsor;
      }
      foreach ($changed as $sponsor)
        DB::set($sponsor);
      Session::pa(new PA("Reordered the list of sponsors."));
    }

    // ------------------------------------------------------------
    // Edit existing one
    // ------------------------------------------------------------
    if (isset($args['edit'])) {
      $sponsor = DB::$V->reqID($args, 'sponsor', DB::$PUB_SPONSOR, "Invalid sponsor to edit.");
      $sponsor->name = DB::$V->reqString($args, 'name', 1, 51, "No name provided for sponsor.");
      $sponsor->url = DB::$V->incString($args, 'url', 1, 256);
      $sponsor->logo = DB::$V->incID($args, 'logo', DB::$PUB_FILE_SUMMARY);
      if ($sponsor->logo !== null && substr($sponsor->logo->filetype, 0, 6) != 'image/')
        throw new SoterException("Only images may be used for the sponsor logo.");
      DB::set($sponsor);
      Session::pa(new PA(sprintf("Edited sponsor \"%s\".", $sponsor->name)));
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $sponsor = DB::$V->reqID($args, 'sponsor', DB::$PUB_SPONSOR, "Invalid sponsor to edit.");
      DB::remove($sponsor);
      Session::pa(new PA(sprintf("Removed sponsor \"%s\". Future files will not have this sponsor.", $sponsor->name)));
      $this->redirect($this->page_url);
    }
  }

  private function fillForm(XForm $f, Pub_Sponsor $sponsor) {
    $f->add(new FReqItem("Name:", new XTextInput('name', $sponsor->name, array('maxlength'=>50))));
    $f->add(new FItem("URL:", new XUrlInput('url', $sponsor->url, array('maxlength'=>255))));
    $f->add(new FItem("Logo:", $sel = new XSelect('logo')));
    $sel->add(new FOption("", ""));
    foreach (DB::getAll(DB::$PUB_FILE_SUMMARY, new DBCond('filetype', 'image/%', DBCond::LIKE)) as $file) {
      $sel->add($opt = new FOption($file->id, $file->id));
      if ($sponsor->logo !== null && $file->id == $sponsor->logo->id)
        $opt->set('selected', 'selected');
    }
  }
}