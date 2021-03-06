<?php
use \users\AbstractUserPane;

/**
 * Manage the list of sponsors to be used on the public site.
 *
 * @author Dayan Paez
 * @version thedate
 */
class SponsorsManagement extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("Sponsors list", $user);
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Edit existing?
    // ------------------------------------------------------------
    if (isset($args['r'])) {
      try {
        $sponsor = DB::$V->reqID($args, 'r', DB::T(DB::PUB_SPONSOR), "Invalid sponsor requested.");
        $this->PAGE->addContent($p = new XPort("Edit " . $sponsor->name));
        $p->add($f = $this->createForm());
        $this->fillForm($f, $sponsor);
        $f->add($xp = new XSubmitP('edit', "Edit"));
        $xp->add(" ");
        $xp->add(new XA($this->link(), "Cancel"));
        $xp->add(new XHiddenInput('sponsor', $sponsor->id));

        $this->PAGE->addContent($p = new XPort("Delete " . $sponsor->name));
        $p->add(new XP(array(), sprintf("To delete \"%s\" as a sponsor, click the button below. Note that previously generated files will not be affected, only files generated after the deletion.", $sponsor->name)));
        $p->add($f = $this->createForm());
        $f->add($xp = new XSubmitP('delete', "Delete", array(), true));
        $xp->add(new XHiddenInput('sponsor', $sponsor->id));
        return;
      } catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
        WS::go($this->link());
      }
    }

    // ------------------------------------------------------------
    // Current list
    // ------------------------------------------------------------
    $curr = DB::getAll(DB::T(DB::PUB_SPONSOR));
    if (count($curr) > 0) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current list"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Edit the order of the sponsors by specifying the order in the first cell, or (if available) dragging the rows around. Click \"Edit order\" to save changes. To delete a sponsor, click \"Edit\" in the last column."));

      if (DB::g(STN::REGATTA_SPONSORS)) {
        $f->add(new XP(array(),
                       array("To make a sponsor available to use as a ",
                             new XStrong("regatta-level sponsor"),
                             ", assign a picture for the \"Regatta Logo\" field.")));
      }

      $headers = array("Order", "#", "Name", "URL", "Logo");
      if (DB::g(STN::REGATTA_SPONSORS))
        $headers[] = "Regatta Logo";
      $headers[] = "";

      $f->add($tab = new XQuickTable(array('class'=>'sponsors-list', 'id'=>'divtable'), $headers));
      foreach ($curr as $i => $sponsor) {
        $url = "";
        if ($sponsor->url !== null)
          $url = new XA($sponsor->url, $sponsor->url, array('target'=>'_blank'));
        $log = "";
        if ($sponsor->logo !== null) {
          $file = $sponsor->logo->getFile();
          $log = new XImg(sprintf('data:%s;base64,%s', $file->filetype, base64_encode($file->filedata)), "");
        }
        $reg_log = "";
        if ($sponsor->regatta_logo !== null) {
          $file = $sponsor->regatta_logo->getFile();
          $reg_log = new XImg(sprintf('data:%s;base64,%s', $file->filetype, base64_encode($file->filedata)), "");
        }

        $row = array(
          new XTD(array(),
                  array(new XNumberInput('order[]', ($i + 1), 1, count($curr), 1, array('size'=>2)),
                        new XHiddenInput('sponsor[]', $sponsor->id))),
          new XTD(array('class'=>'drag'), ($i + 1)),
          $sponsor->name,
          $url,
          $log);
        if (DB::g(STN::REGATTA_SPONSORS))
          $row[] = $reg_log;
        $row[] = new XA($this->link(array('r'=>$sponsor->id)), "Edit");

        $tab->addRow($row, array('class'=>'sortable row'.($i % 2)));
      }
      $f->add(new XSubmitP('reorder', "Edit order"));
    }

    // ------------------------------------------------------------
    // Add new one
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add sponsor"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(),
                   array("Each sponsor should have a name, an optional URL. In order to be used as a site-wide sponsor, a logo must be assigned. The logo to be used must be one of the public files. Visit ",
                         new XA(WS::link('/files'), "Public files"),
                         " to upload a logo first.")));

    if (DB::g(STN::REGATTA_SPONSORS)) {
      $f->add(new XWarning(
                     "Note: in order to make the sponsor available at the regatta level, you must include an image in the \"Regatta Logo\" field."));
    }

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
      $sponsor->logo = DB::$V->incID($args, 'logo', DB::T(DB::PUB_FILE_SUMMARY));
      if ($sponsor->logo !== null && substr($sponsor->logo->filetype, 0, 6) != 'image/')
        throw new SoterException("Only images may be used for the sponsor logo.");
      $sponsor->regatta_logo = DB::$V->incID($args, 'regatta_logo', DB::T(DB::PUB_FILE_SUMMARY));
      if ($sponsor->regatta_logo !== null && substr($sponsor->regatta_logo->filetype, 0, 6) != 'image/')
        throw new SoterException("Only images may be used for the regatta sponsor logo.");
      $sponsor->relative_order = count(DB::getAll(DB::T(DB::PUB_SPONSOR))) + 1;
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
        $sponsor = DB::$V->reqID($map, $i, DB::T(DB::PUB_SPONSOR), "Invalid sponsor provided: " . $id);
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
      $sponsor = DB::$V->reqID($args, 'sponsor', DB::T(DB::PUB_SPONSOR), "Invalid sponsor to edit.");
      $sponsor->name = DB::$V->reqString($args, 'name', 1, 51, "No name provided for sponsor.");
      $sponsor->url = DB::$V->incString($args, 'url', 1, 256);
      $sponsor->logo = DB::$V->incID($args, 'logo', DB::T(DB::PUB_FILE_SUMMARY));
      if ($sponsor->logo !== null && substr($sponsor->logo->filetype, 0, 6) != 'image/')
        throw new SoterException("Only images may be used for the sponsor logo.");
      DB::set($sponsor);
      Session::pa(new PA(sprintf("Edited sponsor \"%s\".", $sponsor->name)));
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $sponsor = DB::$V->reqID($args, 'sponsor', DB::T(DB::PUB_SPONSOR), "Invalid sponsor to edit.");
      DB::remove($sponsor);
      Session::pa(new PA(sprintf("Removed sponsor \"%s\". Future files will not have this sponsor.", $sponsor->name)));
      WS::go($this->link());
    }
  }

  private function fillForm(XForm $f, Pub_Sponsor $sponsor) {
    $f->add(new FReqItem("Name:", new XTextInput('name', $sponsor->name, array('maxlength'=>50))));
    $f->add(new FItem("URL:", new XUrlInput('url', $sponsor->url, array('maxlength'=>255))));
    $f->add(new FItem("Logo:", $sel = new XSelect('logo'), "Image must be present for sponsor to be listed on public site footer."));
    $sel->add(new FOption("", ""));

    $reg_sel = null;
    if (DB::g(STN::REGATTA_SPONSORS)) {
      $f->add(new FItem("Regatta Logo:", $reg_sel = new XSelect('regatta_logo'), "Regatta logo images should be no larger than 50px high."));
      $reg_sel->add(new FOption("", ""));
    }

    foreach (DB::getAll(DB::T(DB::PUB_FILE_SUMMARY), new DBCond('filetype', 'image/%', DBCond::LIKE)) as $file) {
      $sel->add($opt = new FOption($file->id, $file->id));
      if ($sponsor->logo !== null && $file->id == $sponsor->logo->id)
        $opt->set('selected', 'selected');

      if ($reg_sel !== null) {
        $reg_sel->add($opt = new FOption($file->id, $file->id));
        if ($sponsor->regatta_logo !== null && $file->id == $sponsor->regatta_logo->id)
          $opt->set('selected', 'selected');
      }
    }
  }
}