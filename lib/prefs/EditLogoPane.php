<?php
/**
 * Defines one class, the page for editing school logos.
 *
 * @package prefs
 */

require_once('users/AbstractUserPane.php');

/**
 * EditLogoPane: an editor for a school's logo.
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class EditLogoPane extends AbstractUserPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(User $usr, School $school) {
    parent::__construct("School logo", $usr, $school);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("School logo"));
    $p->add(new XP(array(), "Use this function to upload a new logo to use with your school. This logo will replace all uses of the logo throughout TechScore."));

    $p->add(new XP(array(), "Most picture formats are allowed, but files can be no larger than 200 KB in size. For best results use an image with a transparent background, by either using a PNG or GIF file format."));

    $p->add($para = new XP(array(), "Please allow up to 8 hours after uploading for the new logo to appear on the public site."));
    // Current logo
    if ($this->SCHOOL->burgee) {
      $url = sprintf('data:image/png;base64,%s', $this->SCHOOL->burgee->filedata);
      $para->add(sprintf("The current logo for %s is shown below. If you do not see an image below, you may need to upgrade your browser.", $this->SCHOOL->name));
      
      $p->add(new XP(array('style'=>'text-align:center;'),
		     new XImg($url, $this->SCHOOL->nick_name)));
    }
    else {
      $para->add("There is currently no logo for this school on file.");
    }

    // Form
    $p->add($form = new XFileForm(sprintf("/pedit/%s/logo", $this->SCHOOL->id)));
    $form->add(new XHiddenInput("MAX_FILE_SIZE","200000"));
    $form->add(new FItem("Picture:", new XFileInput("logo_file")));
    $form->add(new XSubmitInput("upload", "Upload"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    require_once('Thumbnailer.php');

    // Check $args
    if (!isset($args['upload'])) {
      return;
    }

    // Check that file was uploaded
    if ($_FILES["logo_file"]["error"] > 0) {
      $mes = 'Error while uploading file. Please try again.';
      Session::pa(new PA($mes, PA::E));
      return;
    }

    // Check size
    if ($_FILES["logo_file"]["size"] > 200000) {
      Session::pa(new PA("File is too large.", PA::E));
      return;
    }

    // Create thumbnail
    $th = $_FILES["logo_file"]["tmp_name"].".thumb";
    $tn = new Thumbnailer(100, 100);
    if (!$tn->resize($_FILES["logo_file"]["tmp_name"], $th)) {
      Session::pa(new PA("Invalid image file.", PA::E));
      return;
    }

    // Update database
    $this->SCHOOL->burgee = new Burgee();
    $this->SCHOOL->burgee->filedata = base64_encode(file_get_contents($th));
    $this->SCHOOL->burgee->last_updated = new DateTime("now");
    Preferences::updateSchool($this->SCHOOL, "burgee", Session::g('user'));
    Session::pa(new PA("Updated school logo."));

    // Notify, this needs to change!
  }
}
?>