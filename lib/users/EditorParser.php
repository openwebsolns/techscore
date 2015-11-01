<?php
use \users\AbstractUserPane;

/**
 * Parses the given POST plain text using internal editor
 *
 * @author Dayan Paez
 * @created 2014-12-08
 */
class EditorParser extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("Parse plain text", $user);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Enter plain text"));
    $p->add($f = $this->createForm());
    $f->add(new XDiv(array(),
                     array(new XTextArea('content', "",
                                         array('id'=>'content', 'cols'=>'80', 'rows'=>'20')))));
    $f->add($xp = new XSubmitP('parse', "Parse"));
  }

  public function process(Array $args) {
    $input = DB::$V->reqString($args, 'content', 1, 10000);
    require_once('xml5/TSEditor.php');
    $DPE = new TSEditor();
    $P = new XPage("Parsed content");
    $P->body->add(new XDiv(array(), $DPE->parse($input)));
    $P->printXML();
    exit;
  }
}
?>