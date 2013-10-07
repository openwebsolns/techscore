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
    $p->add(new XP(array(), "Upload a logo to use with your school. This logo will replace all uses of the logo throughout " . Conf::$NAME . "."));

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

    $finfo = new FInfo(FILEINFO_MIME_TYPE);
    $res = $finfo->file($file['tmp_name']);
    if ($res != 'image/png' && $res != 'image/gif')
      throw new SoterException("Only PNG and GIF images are allowed.");

    // Create thumbnail
    set_error_handler(function($n, $s) {
        throw new SoterException("Invalid image file.");
      }, E_WARNING);
    $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    restore_error_handler();

    if ($src === false)
      throw new SoterException("Invalid image file.");

    $size = getimagesize($file['tmp_name']);
    if ($size[0] < 32 || $size[1] < 32)
      throw new SoterException("Image too small.");

    // resize image to fix in bounding boxes
    $full = $this->resizeToSize($src, $size[0], $size[1], 180, 120);
    $small = $this->resizeToSize($src, $size[0], $size[1], 60, 40);
    imagedestroy($src);
    if ($full === null || $small === null)
      throw new SoterException("Invalid image conversion.");

    // Update database: first create the burgee, then assign it to the
    // school object (for history control, mostly)
    $full->last_updated = new DateTime();
    $full->school = $this->SCHOOL;
    $full->updated_by = Session::g('user');
    DB::set($full);

    $small->last_updated = new DateTime();
    $small->school = $this->SCHOOL;
    $small->updated_by = Session::g('user');
    DB::set($small);

    // If this is the first time a burgee is added, then notify all
    // public regattas for which this school has participated so that
    // they can be regenerated!
    require_once('public/UpdateManager.php');
    if ($this->SCHOOL->burgee === null) {
      $affected = 0;
      foreach ($this->SCHOOL->getRegattas() as $reg) {
        UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_DETAILS);
        $affected++;
      }
      if ($affected > 0)
        Session::pa(new PA(sprintf("%d public regatta(s) will be updated.", $affected)));
    }

    $this->SCHOOL->burgee = $full;
    $this->SCHOOL->burgee_small = $small;
    DB::set($this->SCHOOL);
    Session::pa(new PA("Updated school logo."));
    UpdateManager::queueSchool($this->SCHOOL, UpdateSchoolRequest::ACTIVITY_BURGEE);
  }

  private function resizeToSize($src, $origX, $origY, $boundX, $boundY) {
    $width = $origX;
    $height = $origY;

    $ratio = min(($boundX / $origX), ($boundY / $origY));

    if ($ratio < 1) {
      $width = floor($ratio * $width);
      $height = floor($ratio * $height);
    }

    $dstX = floor(($boundX - $width) / 2);
    $dstY = floor(($boundY - $height) / 2);

    // create transparent destination image
    $dst = imagecreatetruecolor($boundX, $boundY);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefill($dst, 0, 0, $trans);

    if (imagecopyresampled($dst, $src,
                           $dstX, $dstY,      // destination upper-left
                           0, 0,              // source upper-left
                           $width, $height,   // destination
                           $origX, $origY) === false)
      throw new SoterException("Unable to create new burgee image.");

    ob_start();
    imagepng($dst, null, 9, PNG_ALL_FILTERS);
    $txt = ob_get_contents();
    ob_end_clean();
    imagedestroy($dst);

    if ($txt == "") {
      return null;
    }

    $burg = new Burgee();
    $burg->filedata = base64_encode($txt);
    $burg->width = $boundX;
    $burg->height = $boundY;
    return $burg;
  }
}
?>