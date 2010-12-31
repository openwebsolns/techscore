<?php
/**
 * Defines one class, the page for editing school logos.
 *
 * @package prefs
 */

/**
 * EditLogoPane: an editor for a school's logo.
 *
 * @author Dayan Paez
 * @created 2009-10-14
 */
class EditLogoPane extends AbstractPrefsPane {

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
  public function fillHTML() {
    $this->PAGE->addContent($p = new Port("School logo"));
    $p->addChild(new Para("Use this function to upload a new logo to use " .
			  "with your school. This logo will replace all " .
			  "uses of the logo throughout TechScore."));

    $p->addChild(new Para("Most picture formats are allowed, but files can " .
			  "be no larger than 200 KB in size. For best results " .
			  "use an image with a transparent background, by " .
			  "either using a PNG or GIF file format."));
    // Current logo
    if ($this->SCHOOL->burgee) {
      $url = sprintf("img/schools/%s.png", $this->SCHOOL->id);
      $p->addChild(new Para(sprintf("The current logo for %s:", $this->SCHOOL->name)));
      $p->addChild(new GenericElement("center",
				      array(new Image($url, array("alt"=>$this->SCHOOL->nick_name)))));
    }
    else {
      $p->addChild(new Para("There is currently no logo for this school on file."));
    }

    // Form
    $p->addChild($form = new Form(sprintf("pedit/%s/logo", $this->SCHOOL->id), "post",
				  array("enctype"=>"multipart/form-data")));
    $form->addChild(new FHidden("MAX_FILE_SIZE","200000"));
    $form->addChild(new FItem("Picture:", new FFile("logo_file")));
    $form->addChild(new FSubmit("upload", "Upload"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {

    // Check $args
    if (!isset($args['upload'])) {
      return;
    }

    // Check that file was uploaded
    if ($_FILES["logo_file"]["error"] > 0) {
      $mes = 'Error while uploading file. Please try again.';
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return;
    }

    // Check size
    if ($_FILES["logo_file"]["size"] > 200000) {
      $this->announce(new Announcement("File is too large.", Announcement::ERROR));
      return;
    }

    // Create thumbnail
    $th = $_FILES["logo_file"]["tmp_name"].".thumb";
    $tn = new Thumbnailer(100, 100);
    if (!$tn->resize($_FILES["logo_file"]["tmp_name"], $th)) {
      $this->announce(new Announcement("Invalid image file.", Announcement::ERROR));
      return;
    }

    // Update database
    $this->SCHOOL->burgee = new Burgee();
    $this->SCHOOL->burgee->filedata = base64_encode(file_get_contents($th));
    $this->SCHOOL->burgee->last_updated = new DateTime("now");
    if (Preferences::updateSchool($this->SCHOOL, "burgee")) {
      $this->announce(new Announcement("Updated school logo."));
    }
    else {
      $mes = "Unable to update school logo in database.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
    }

    // Notify, this needs to change!
    /*
    $url = sprintf('%s/%s', HOME, $filename);
    $command = sprintf('/usr/bin/php ' .
		       '/var/local/ts/_notify_school.cli.php %s %s',
		       $this->SCHOOL->id,
		       $url);
    exec("date >> /var/local/ts/_notify_school.log; $command >> /var/local/ts/_notify_school.log &");
    return;
    */
  }
}
?>