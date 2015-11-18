<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('conf.php');

/**
 * Edit the list of documents associated with a regatta
 *
 * @author Dayan Paez
 * @created 2013-11-21
 */
class NoticeBoardPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Notice board", $user, $reg);
  }

  private function fillDocument(Document_Summary $doc, Array $args) {
    $categories = Document::getCategories();

    $this->PAGE->addContent($p = new XPort("Edit document"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Name:", new XStrong($doc->name)));
    $form->add(new FItem("Category:", new XStrong($categories[$doc->category])));
    $form->add(new FItem("Description:", new XTextArea('description', $doc->description, array('placeholder'=>"Optional, but highly recommended, description."))));
    $form->add($this->createRaceFitem($doc));

    $download = new XA($this->link('notices', array('file'=>$doc->url)), "Download");
    if (substr($doc->filetype, 0, 6) == 'image/')
      $download = $doc->asImg($this->link('notices', array('file'=>$doc->url)), $doc->description, array('class'=>'document-race-preview'));
    $form->add(new FItem("Document:", $download));

    $form->add($x = new XSubmitP('edit-document', "Edit"));
    $x->add(" ");
    $x->add(new XA($this->link('notices'), "Cancel"));
    $x->add(new XHiddenInput('file', $doc->url));
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Download file?
    // ------------------------------------------------------------
    if (isset($args['file'])) {
      $file = $this->REGATTA->getDocument($args['file'], true);
      if ($file !== null) {
        header(sprintf('Content-Type: %s', $file->filetype));
        echo $file->filedata;
        exit(0);
      }
      else
        Session::pa(new PA(sprintf("Invalid file requested: %s.", $args['file']), PA::E));
    }

    // ------------------------------------------------------------
    // Edit file?
    // ------------------------------------------------------------
    elseif (isset($args['edit'])) {
      $file = $this->REGATTA->getDocument($args['edit']);
      if ($file !== null) {
        $this->fillDocument($file, $args);
        return;
      }
      else
        Session::pa(new PA(sprintf("Invalid file requested: %s.", $args['edit']), PA::E));
    }

    $categories = Document::getCategories();
    // ------------------------------------------------------------
    // List/upload files
    // ------------------------------------------------------------
    $this->PAGE->addContent(new XP(array(),
                   array("Items posted here will be published under the \"Notice Board\" link for this regatta. This is an appropriate place to post Sailing Instructions (SI), notices of race, and protest notices. This is ", new XStrong("not"), " the place to post daily summaries.")));
    $size_limit = DB::g(STN::NOTICE_BOARD_SIZE);
    $this->PAGE->addContent(new XP(array(),
                                   array("Only ", new XStrong("PDF"), ", ", new XStrong("JPG"), ", ", new XStrong("GIF"), ", and ", new XStrong("PNG"), " files are allowed. The maximum file size is ", new XStrong(sprintf("%0.1fMB", $size_limit / 1048576)), " for each file.")));
    $this->PAGE->addContent(new XP(array(),
                   array("Please provide meaningful (unique) names to all the documents, and attach a description whenever possible. Also, order them appropriately. ",
                         new XStrong("Make sure to specify the correct notice category!"))));

    $this->PAGE->addContent(new XH3("Course formats"));
    $this->PAGE->addContent(new XP(array(),
                                   array("Use the \"Course format\" category to upload an image and a text description of the course layout. The image will be published on the public site on the notice board as well as on the home page until the regatta gets under way.")));
    $this->PAGE->addContent(new XWarning(
                                   array(new XStrong("Note:"), " There may only be one course format per race in the regatta.")));

    $files = $this->REGATTA->getDocuments();
    if (count($files) > 0) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current items"));

      $p->add(new XP(array(), "Click on document name to edit description or races."));
      $p->add($f = $this->createForm());
      $f->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'doctable full'),
                                     array("Order", "#", "Name", "Description", "Category", "Races", "Download", "Delete?")));
      foreach ($files as $i => $file) {
        $tab->addRow(array(new XTD(array(),
                                   array(new XNumberInput('order[]', ($i + 1), 1, count($files), 1, array('size'=>2)),
                                         new XHiddenInput('document[]', $file->url))),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           new XA($this->link('notices', array('edit'=>$file->url)), $file->name),
                           $file->description,
                           $categories[$file->category],
                           $this->createRaceRange($file),
                           new XA($this->link('notices', array('file'=>$file->url)), "Download"),
                           new FCheckbox('delete[]', $file->url)),
                     array('class'=>'sortable row' . ($i % 2)));
      }
      $f->add(new XSubmitP('reorder', "Order/Delete"));
    }

    $this->PAGE->addContent($p = new XPort("New notice"));
    $p->add($f = $this->createFileForm());
    $f->add(new XHiddenInput('MAX_FILE_SIZE', $size_limit));
    $f->add(new FReqItem("Name:", new XTextInput('name', "", array('maxlength'=>100))));
    $f->add(new FItem("Description:", new XTextArea('description', "", array('placeholder'=>"Optional, but highly recommended, description."))));
    $f->add(new FReqItem("Category:", XSelect::fromArray('category', $categories)));

    // Races?
    $f->add($this->createRaceFitem());

    $f->add(new FReqItem("Document:", new XFileInput('file')));
    $f->add(new XSubmitP('upload', "Add document"));
  }

  public function process(Array $args) {
    $categories = Document::getCategories();
    // ------------------------------------------------------------
    // Add new
    // ------------------------------------------------------------
    if (isset($args['upload']) || isset($args['edit-document'])) {
      $edit = isset($args['edit-document']);

      if ($edit) {
        $doc = $this->REGATTA->getDocument(DB::$V->reqString($args, 'file', 1, 101, "Missing document to edit."));
        if ($doc === null)
          throw new SoterException("Invalid document to edit specified.");
      }
      else {
        $file = DB::$V->reqFile($args, 'file', 1, DB::g(STN::NOTICE_BOARD_SIZE), "No document provided, or document too large.");
        $info = new FInfo(FILEINFO_MIME_TYPE);

        $doc = new Document();
        $doc->filetype = $info->file($file['tmp_name']);
        if (!in_array($doc->filetype, array('application/pdf', 'image/jpeg', 'image/gif', 'image/png')))
          throw new SoterException("Invalid file type. Must be PDF, JPG, GIF, or PNG.");

        $doc->name = DB::$V->reqString($args, 'name', 3, 101, "No name provided, or too short (must be 3-100 characters long).");
        $doc->category = DB::$V->reqKey($args, 'category', $categories, "Invalid document category.");
        $doc->filedata = file_get_contents($file['tmp_name']);

        // Attempt to retrieve width/height for images
        $type = explode('/', $doc->filetype);
        // Course format must be an image
        if ($doc->category == Document_Summary::CATEGORY_COURSE_FORMAT && $type[0] != 'image') {
          throw new SoterException("Only images are allowed for \"Course format\".");
        }

        if ($type[0] == 'image') {
          $size = getimagesize($file['tmp_name']);
          if ($size !== false) {
            $doc->width = $size[0];
            $doc->height = $size[1];
          }
        }
      }

      $doc->description = DB::$V->incString($args, 'description', 1, 16000);
      $doc->author = $this->USER;

      // Races?
      $races = array();
      foreach ($this->REGATTA->getDivisions() as $div) {
        $range = DB::$V->incString($args, 'races-' . $div, 1);
        foreach (DB::parseRange($range) as $num) {
          $race = $this->REGATTA->getRace($div, $num);
          if ($race !== null) {
            if ($doc->category != Document::CATEGORY_COURSE_FORMAT
                || ($other = $this->REGATTA->getRaceCourseFormat($race)) === null
                || $other->id == $doc->id) {
              $races[] = $race;
            }
          }
        }
      }

      // Any course formats for "all" races?
      if ($doc->category == Document::CATEGORY_COURSE_FORMAT && count($races) == 0) {
        $others = $this->REGATTA->getDocuments(false, $doc->category);
        if (count($others) > 0 && $others[0]->id != $doc->id)
          throw new SoterException("Only one course format is allowed per race.");
      }

      if ($edit)
        DB::set($doc);
      else
        $this->REGATTA->addDocument($doc);
      $this->REGATTA->setDocumentRaces($doc, $races);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DOCUMENT, $doc->url);

      $mes = ($edit) ? "Edited" : "Added";
      Session::pa(new PA(sprintf("%s %s document \"%s\".", $mes, $categories[$doc->category], $doc->name)));
    }

    // ------------------------------------------------------------
    // Reorder/Delete
    // ------------------------------------------------------------
    if (isset($args['reorder'])) {
      $docs = array();
      foreach ($this->REGATTA->getDocuments() as $doc)
        $docs[$doc->url] = $doc;

      // Delete?
      $deleted = array();
      foreach (DB::$V->incList($args, 'delete') as $url) {
        if (!isset($docs[$url]))
          throw new SoterException("Invalid file provided for deletion.");

        $deleted[$url] = $docs[$url]->name;
        $this->REGATTA->deleteDocument($url);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DOCUMENT, $url);
      }
      if (count($deleted) > 0)
        Session::pa(new PA(sprintf("Removed file(s): %s.", implode(", ", $deleted))));

      // Reorder
      $urls = DB::$V->reqList($args, 'document', count($docs), "Invalid list of teams to re-order.");
      $ord = DB::$V->incList($args, 'order', count($urls));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $urls);

      $num = 1;
      $changed = array();
      foreach ($urls as $url) {
        if (!isset($docs[$url]))
          throw new SoterException("Invalid document provided while re-ordering: $url.");
        $doc = $docs[$url];
        unset($docs[$url]);
        if (isset($deleted[$url]))
          continue;

        if ($doc->relative_order != $num) {
          $doc->relative_order = $num;
          $changed[] = $doc;
        }

        $num++;
      }

      if (count($changed) > 0) {
        foreach ($changed as $doc) {
          DB::set($doc);
          UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DOCUMENT, $doc->url);
        }
        Session::pa(new PA("Reordered the document list."));
      }
    }
  }

  /**
   * Creates a display-ready representation of races for given document
   *
   */
  private function createRaceRange(Document_Summary $file) {
    $no_divs = $this->REGATTA->getEffectiveDivisionCount() == 1;

    $div = null;
    if ($no_divs)
      $div = Division::A();

    $races = $this->REGATTA->getDocumentRaces($file, $div);
    if (count($races) == 0)
      return new XSpan("All", array('class'=>'document-races'));

    // Distribute race by division
    $by_divs = array();
    foreach ($races as $race) {
      $div = (string)$race->division;

      if (!isset($by_divs[$div]))
        $by_divs[$div] = array();
      $by_divs[$div][] = $race->number;
    }

    if ($no_divs)
      return new XSpan(DB::makeRange($by_divs[(string)Division::A()]), array('class'=>'document-races'));

    $list = new XUl(array('class'=>'document-races-list'));
    foreach ($by_divs as $div => $nums) {
      $list->add(new XLi(array(new XStrong($div),
                               new XSpan(DB::makeRange($nums), array('class'=>'document-races')))));
    }
    return $list;
  }

  private function createRaceFitem(Document_Summary $doc = null) {
    if ($this->REGATTA->getEffectiveDivisionCount() == 1) {
      $val = array();
      if ($doc !== null) {
        foreach ($this->REGATTA->getDocumentRaces($doc, Division::A()) as $race)
          $val[] = $race->number;
      }
      return new FItem("Races:", new XRangeInput('races-A', DB::makeRange($val)), "Blank means the document applies to \"All races\".");
    }

    $f = new FItem("Races by division:", $ul = new XUl(array('class'=>'inline-list')), "Leave all blank to indicate \"All races\".");
    foreach ($this->REGATTA->getDivisions() as $div) {
      $nums = array();
      if ($doc !== null) {
        foreach ($this->REGATTA->getDocumentRaces($doc, $div) as $race)
          $nums[] = $race->number;
      }
        
      $id = 'races-' . $div;
      $ul->add(new XLi(array(new XLabel($id, $div), new XTextInput($id, DB::makeRange($nums), array('id'=>$id))), array('class'=>'document-races-input')));
    }
    return $f;
  }
}
?>