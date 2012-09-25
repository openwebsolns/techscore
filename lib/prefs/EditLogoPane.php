<?php
/**
 * Defines one class, the page for editing school logos.
 *
 * @package prefs
 */

require_once('prefs/AbstractPrefsPane.php');

/**
 * EditLogoPane: an editor for a school's logo.
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class EditLogoPane extends AbstractPrefsPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(Account $usr) {
    parent::__construct("School logo", $usr);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort($this->SCHOOL . " logo"));
    $p->add(new XP(array(), "Use this function to upload a new logo to use with your school. This logo will replace all uses of the logo throughout " . Conf::$NAME . "."));

    $p->add(new XP(array(), "Most picture formats are allowed, but files can be no larger than 200 KB in size. For best results use an image with a transparent background, by either using a PNG or GIF file format."));

    $p->add($para = new XP(array(), "Please allow up to 8 hours after uploading for the new logo to appear on the public site."));
    // Current logo
    if ($this->SCHOOL->burgee !== null) {
      $para->add(sprintf("The current logo for %s is shown below. If you do not see an image below, you may need to upgrade your browser.", $this->SCHOOL));

      $p->add(new XP(array('style'=>'text-align:center;'),
                     new XImg('data:image/png;base64,'.$this->SCHOOL->burgee->filedata, $this->SCHOOL->nick_name)));
    }
    else {
      $para->add(" There is currently no logo for this school on file.");
    }

    // Form
    $p->add($form = new XFileForm(sprintf("/pedit/%s/logo", $this->SCHOOL->id)));
    $form->add(new XHiddenInput("MAX_FILE_SIZE","200000"));
    $form->add(new FItem("Picture:", new XFileInput("logo_file")));
    $form->add(new XSubmitP("upload", "Upload"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    require_once('Thumbnailer.php');

    $file = DB::$V->reqFile($_FILES, 'logo_file', 1, 200000, "Error or missing upload file. Please try again.");

    // Create thumbnail
    $th = $file['tmp_name'].'.thumb';
    $tn = new Thumbnailer(100, 100);
    if (!$tn->resize($file['tmp_name'], $th))
      throw new SoterException("Invalid image file.");

    // Update database: first create the burgee, then assign it to the
    // school object (for history control, mostly)
    $burg = new Burgee();
    $burg->filedata = base64_encode(file_get_contents($th));
    $burg->last_updated = new DateTime();
    $burg->school = $this->SCHOOL;
    $burg->updated_by = Session::g('user');
    DB::set($burg);

    $this->SCHOOL->burgee = $burg;
    DB::set($this->SCHOOL);
    Session::pa(new PA("Updated school logo."));
  }
}
?>