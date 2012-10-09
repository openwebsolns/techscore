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

    // Current logo
    if ($this->SCHOOL->burgee !== null) {
      $p->add(new XP(array(), sprintf("The current logo for %s is shown below. If you do not see an image below, you may need to upgrade your browser.", $this->SCHOOL)));

      $p->add(new XP(array('style'=>'text-align:center;'),
                     new XImg('data:image/png;base64,'.$this->SCHOOL->burgee->filedata, $this->SCHOOL->nick_name)));
    }
    else {
      $p->add(new XP(array(), "There is currently no logo for this school on file."));
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
    $file = DB::$V->reqFile($_FILES, 'logo_file', 1, 200000, "Error or missing upload file. Please try again.");

    // Create thumbnail
    if (($src = imagecreatefromstring(file_get_contents($file['tmp_name']))) === false)
      throw new SoterException("Invalid image file.");

    $size = getimagesize($file['tmp_name']);
    if ($size[0] < 32 || $size[1] < 32)
      throw new SoterException("Image too small.");

    // resize image to fix in bounding box 100x100
    $width = $size[0];
    $height = $size[1];

    $boundX = 100;
    $boundY = 100;

    $ratio = min(($boundX / $width), ($boundY / $height));
    if ($ratio < 1) {
      $width = floor($ratio * $width);
      $height = floor($ratio * $height);
    }

    // create transparent destination image
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefill($dst, 0, 0, $trans);

    if (imagecopyresized($dst, $src,
                         0, 0,              // destination upper-left
                         0, 0,              // source upper-left
                         $width, $height,   // destination
                         $size[0], $size[1]) === false)
      throw new SoterException("Unable to create new burgee image.");

    imagedestroy($src);
    ob_start();
    imagepng($dst);
    $txt = ob_get_contents();
    ob_end_clean();
    imagedestroy($dst);

    if ($txt == "")
      throw new SoterException("Invalid image conversion.");

    // Update database: first create the burgee, then assign it to the
    // school object (for history control, mostly)
    $burg = new Burgee();
    $burg->filedata = base64_encode($txt);
    $burg->last_updated = new DateTime();
    $burg->school = $this->SCHOOL;
    $burg->updated_by = Session::g('user');
    DB::set($burg);

    // If this is the first time a burgee is added, then notify all
    // public regattas for which this school has participated so that
    // they can be regenerated!
    require_once('public/UpdateManager.php');
    if ($this->SCHOOL->burgee === null) {
      $affected = 0;
      foreach ($this->SCHOOL->getRegattas() as $reg) {
        if ($reg->type != Regatta::TYPE_PERSONAL) {
          UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_DETAILS);
          $affected++;
        }
      }
      if ($affected > 0)
        Session::pa(new PA(sprintf("%d public regatta(s) will be updated.", $affected)));
    }

    $this->SCHOOL->burgee = $burg;
    DB::set($this->SCHOOL);
    Session::pa(new PA("Updated school logo."));
    UpdateManager::queueSchool($this->SCHOOL, UpdateSchoolRequest::ACTIVITY_BURGEE);
  }
}
?>