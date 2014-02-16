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

    $files = $this->REGATTA->getDocuments();
    if (count($files) > 0) {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current items"));

      $p->add($f = $this->createForm());
      $f->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'doctable'),
                                     array("Order", "#", "Name", "Description", "Category", "Download", "Delete?")));
      foreach ($files as $i => $file) {
        $tab->addRow(array(new XTD(array(),
                                   array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                         new XHiddenInput('document[]', $file->url))),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           new XStrong($file->name),
                           new XTD(array('style'=>'max-width:15em'), $file->description),
                           $categories[$file->category],
                           new XA($this->link('notices', array('file'=>$file->url)), "Download"),
                           new XCheckboxInput('delete[]', $file->url)),
                     array('class'=>'sortable row' . ($i % 2)));
      }
      $f->add(new XSubmitP('reorder', "Order/Delete"));
    }

    $this->PAGE->addContent($p = new XPort("New notice"));
    $p->add($f = $this->createFileForm());
    $f->add(new XHiddenInput('MAX_FILE_SIZE', $size_limit));
    $f->add(new FItem("Name:", new XTextInput('name', "", array('maxlength'=>100))));
    $f->add(new FItem("Description:", new XTextArea('description', "", array('placeholder'=>"Optional, but highly recommended, description."))));
    $f->add(new FItem("Category:", XSelect::fromArray('category', $categories)));
    $f->add(new FItem("Document:", new XFileInput('file')));
    $f->add(new XSubmitP('upload', "Add document"));
  }

  public function process(Array $args) {
    $categories = Document::getCategories();
    // ------------------------------------------------------------
    // Add new
    // ------------------------------------------------------------
    if (isset($args['upload'])) {
      $file = DB::$V->reqFile($_FILES, 'file', 1, DB::g(STN::NOTICE_BOARD_SIZE), "No document provided, or document too large.");
      $info = new FInfo(FILEINFO_MIME_TYPE);

      $doc = new Document();
      $doc->filetype = $info->file($file['tmp_name']);
      if (!in_array($doc->filetype, array('application/pdf', 'image/jpeg', 'image/gif', 'image/png')))
        throw new SoterException("Invalid file type. Must be PDF, JPG, GIF, or PNG.");

      $doc->name = DB::$V->reqString($args, 'name', 3, 101, "No name provided, or too short (must be 3-100 characters long).");
      $doc->description = DB::$V->incString($args, 'description', 1, 16000);
      $doc->category = DB::$V->reqKey($args, 'category', $categories, "Invalid document category.");
      $doc->author = $this->USER;
      $doc->filedata = file_get_contents($file['tmp_name']);

      // Attempt to retrieve width/height for images
      if (substr($doc->filetype, 0, 6) == 'image/') {
        $size = getimagesize($file['tmp_name']);
        if ($size !== false) {
          $doc->width = $size[0];
          $doc->height = $size[1];
        }
      }

      $this->REGATTA->addDocument($doc);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DOCUMENT, $doc->url);
      Session::pa(new PA(sprintf("Added %s document \"%s\".", $categories[$doc->category], $doc->name)));
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
}
?>