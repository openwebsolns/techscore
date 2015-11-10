<?php
use \prefs\AbstractPrefsPane;
use \ui\ImageInputWithPreview;
use \users\utils\burgees\AssociateBurgeesToSchoolHelper;

/**
 * EditLogoPane: an editor for a school's logo.
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class EditLogoPane extends AbstractPrefsPane {

  private $burgeeHelper;

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(Account $usr, School $school) {
    parent::__construct("School logo", $usr, $school);
    $this->page_url = 'logo';
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort($this->SCHOOL . " logo"));
    $p->add(new XP(array(), "Upload a logo to use with your school. This logo will replace all uses of the logo throughout " . DB::g(STN::APP_NAME) . "."));

    $p->add(new XP(array(), "Follow these rules for best results:"));
    $p->add(new XUl(array(),
                    array(new XLi("File can be no larger than 200 KB in size."),
                          new XLi(array("Only ", new XStrong("PNG"), " and ", new XStrong("GIF"), " images are allowed.")),
                          new XLi("The image used should have a transparent background, so that it looks appropriate throught the application."),
                          new XLi(array("All images will be resized to fit in an aspect ratio of 3:2. We ", new XStrong("strongly recommend"), " that the image be properly cropped prior to uploading.")))));

    // Current logo
    if ($this->SCHOOL->burgee !== null) {
      $p->add(new XP(array(), sprintf("The current logo for %s is shown below. If you do not see an image below, you may need to upgrade your browser.", $this->SCHOOL)));

      $p->add($xp = new XP(array('id'=>'burgee-preview'),
                           array(new XImg('data:image/png;base64,'.$this->SCHOOL->burgee->filedata, $this->SCHOOL->nick_name))));
      if ($this->SCHOOL->burgee_small !== null)
        $xp->add(new XImg('data:image/png;base64,'.$this->SCHOOL->burgee_small->filedata, $this->SCHOOL->nick_name . " small"));
    }
    else {
      $p->add(new XP(array(), "There is currently no logo for this school on file."));
    }

    // Form
    $p->add($form = $this->createFileForm());
    $form->add(new XHiddenInput("MAX_FILE_SIZE","200000"));
    $form->add(new FReqItem("New logo:", new ImageInputWithPreview('logo_file')));
    $form->add($xp = new XSubmitP('upload', "Upload"));
    if ($this->SCHOOL->burgee !== null) {
      $xp->add(" ");
      $xp->add(new XSubmitDelete('delete', "Delete", array('onclick'=>'return confirm("Are you sure you wish to delete the logo?");')));
    }
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Upload a new one
    // ------------------------------------------------------------
    if (isset($args['upload'])) {
      $file = DB::$V->reqFile($_FILES, 'logo_file', 1, 200000, "Error or missing upload file. Please try again.");

      $processor = $this->getAssociateBurgeesHelper();
      $processor->setBurgee(
        $this->USER,
        $this->SCHOOL,
        $file['tmp_name']
      );
      Session::pa(new PA("Updated school logo."));
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      require_once('public/UpdateManager.php');

      // If a burgee exists, then update all existing regattas as well
      if ($this->SCHOOL->burgee !== null) {
        UpdateManager::queueSchool($this->SCHOOL, UpdateSchoolRequest::ACTIVITY_DETAILS);

        $affected = 0;
        foreach ($this->SCHOOL->getRegattas() as $reg) {
          UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_DETAILS);
          $affected++;
        }
        if ($affected > 0)
          Session::pa(new PA(sprintf("%d public regatta(s) will be updated.", $affected)));
      }

      $this->SCHOOL->burgee = null;
      $this->SCHOOL->burgee_small = null;
      $this->SCHOOL->burgee_square = null;
      DB::set($this->SCHOOL);
      Session::pa(new PA("Removed school logo."));
      UpdateManager::queueSchool($this->SCHOOL, UpdateSchoolRequest::ACTIVITY_BURGEE);
    }
  }

  /**
   * Inject the processor for burgees.
   *
   * @param BurgeeProcessor $processor the new processor.
   */
  public function setAssociateBurgeesHelper(AssociateBurgeesToSchoolHelper $processor) {
    $this->burgeeHelper = $processor;
  }

  private function getAssociateBurgeesHelper() {
    if ($this->burgeeHelper == null) {
      $this->burgeeHelper = new AssociateBurgeesToSchoolHelper();
    }
    return $this->burgeeHelper;
  }
}
