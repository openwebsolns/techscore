<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manage the static files to be serialized in the public site
 *
 * @author Dayan Paez
 * @created 2013-10-04
 */
class PublicFilesManagement extends AbstractAdminUserPane {

  private static $JS_AUTOLOAD_OPTIONS = array(
    '' => "",
    Pub_File::AUTOLOAD_SYNC => "Sync",
    Pub_File::AUTOLOAD_ASYNC => "Async"
  );


  public function __construct(Account $user) {
    parent::__construct("Public files", $user);
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Download file?
    // ------------------------------------------------------------
    if (isset($args['file'])) {
      $file = DB::getFile($args['file']);
      if ($file !== null) {
        header(sprintf('Content-Type: %s', $file->filetype));
        echo $file->filedata;
        exit(0);
      }
      else
        Session::pa(new PA(sprintf("Invalid file requested: %s.", $args['file']), PA::E));
    }

    // ------------------------------------------------------------
    // List/upload files
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Current files"));
    $p->add($f = $this->createFileForm());
    $files = DB::getAll(DB::T(DB::PUB_FILE_SUMMARY));
    if (count($files) > 0) {
      $f->add($tab = new XQuickTable(array('id'=>'files-table'),
                                     array("Name",
                                           "Type",
                                           "Download",
                                           "Autoload",
                                           "Delete?")));
      foreach ($files as $i => $file) {
        $pre = new XA($this->link(array('file'=>$file->id)), "Download");
        if (substr($file->filetype, 0, 6) == 'image/') {
          $full = $file->getFile();
          $pre = new XImg(sprintf('data:%s;base64,%s', $full->filetype, base64_encode($full->filedata)), "");
          if ($full->width !== null) {
            $pre->set('width', $full->width);
            $pre->set('height', $full->height);
          }
        }

        $auto_inc = "";
        if ($file->filetype == 'application/javascript') {
          // TODO
          $chosen = null;
          if (count($file->options) > 0)
            $chosen = $file->options[0];
          $auto_inc = XSelect::fromArray(sprintf('options[%s]', $file->id),
                                         self::$JS_AUTOLOAD_OPTIONS,
                                         $chosen
                                         );
        }

        $tab->addRow(array($file->id,
                           $file->filetype,
                           $pre,
                           $auto_inc,
                           new FCheckbox('delete[]', $file->id)),
                     array('class'=>'row' . ($i % 2)));
      }
    }

    $f->add(new FItem("New file:", new XFileInput('file[]', array('multiple'=>'multiple'))));
    $f->add(new XSubmitP('upload', "Save changes"));
  }

  public function process(Array $args) {
    if (isset($args['upload'])) {
      require_once('public/UpdateManager.php');

      // ------------------------------------------------------------
      // Delete files?
      // ------------------------------------------------------------
      $deleted = array();
      foreach (DB::$V->incList($args, 'delete') as $id) {
        $file = DB::getFile($id);
        if ($file === null)
          throw new SoterException("Invalid file provided for deletion: " . $id);

        $deleted[] = $id;
        DB::remove($file);
        UpdateManager::queueFile($file);
      }
      if (count($deleted) > 0)
        Session::pa(new PA(sprintf("Removed file(s): %s.", implode(", ", $deleted))));

      // ------------------------------------------------------------
      // Edit file options
      // ------------------------------------------------------------
      $updated = 0;
      foreach (DB::$V->incList($args, 'options') as $id => $option) {
        $file = DB::getFile($id);
        if ($file === null)
          throw new SoterException("Invalid valid whose option to set.");

        if ($file->filetype == 'application/javascript') {
          if (!array_key_exists($option, self::$JS_AUTOLOAD_OPTIONS))
            throw new SoterException("Invalid option provided for Javascript file $file");

          $changed = false;
          if ($option == '') {
            $changed = $file->removeOption(Pub_File::AUTOLOAD_SYNC);
            $changed = $file->removeOption(Pub_File::AUTOLOAD_ASYNC) || $changed;
          } elseif (!$file->hasOption($option)) {
            $file->removeOption(Pub_File::AUTOLOAD_SYNC);
            $file->removeOption(Pub_File::AUTOLOAD_ASYNC);
            $file->addOption($option);
            $changed = true;
          }

          if ($changed) {
            DB::set($file);
            $updated++;
            UpdateManager::queueFile($file);
          }
        }
      }
      if ($updated > 0)
        Session::pa(new PA(sprintf("Updated options for %d file%s.", $updated, ($updated > 0) ? "s" : "")));

      // ------------------------------------------------------------
      // Upload new files
      // ------------------------------------------------------------
      $files = DB::$V->incFiles($_FILES, 'file', 1, 16777215);
      if (count($files) > 0) {
        $finfo = new FInfo(FILEINFO_MIME_TYPE);

        $uploaded = array();
        foreach ($files as $file) {
          $name = $file['name'];

          $type = null;
          $data = file_get_contents($file['tmp_name']);
          $tokens = $this->explodeFilename($name);
          if (count($tokens) > 1) {
            $ext = array_pop($tokens);
            if ($ext == 'js') {
              $type = 'application/javascript';
              $data = $this->compressJavascript($data);
            }
            elseif ($ext == 'css') {
              $type = 'text/css';
              $data = $this->compressCSS($data);
            }
          }
          if ($type === null) {
            $type = $finfo->file($file['tmp_name']);
          }

          $obj = DB::getFile($name);
          if ($obj === null) {
            $obj = new Pub_File();
            $obj->id = $name;
          }
          $obj->filetype = $type;
          $obj->filedata = $data;

          // Attempt to retrieve width/height for images
          if (substr($type, 0, 6) == 'image/') {
            $size = getimagesize($file['tmp_name']);
            if ($size !== false) {
              $obj->width = $size[0];
              $obj->height = $size[1];
            }
          }

          DB::set($obj);
          UpdateManager::queueFile($obj);
          $uploaded[] = $name;
        }

        Session::pa(new PA(sprintf("Uploaded file(s): %s.", implode(", ", $uploaded))));
      }
    }
  }

  private function explodeFilename($name) {
    $tokens = array();
    foreach (explode('.', $name) as $token)
      $tokens[] = $this->sanitizeUrl($token);

    if (count($tokens) == 1)
      return $tokens;
    $lst = array(trim(array_pop($tokens)));
    array_unshift($lst, trim(implode('.', $tokens)));
    return $lst;
  }

  private function sanitizeUrl($seed) {
    // remove spaces, ('s)'s
    $url = str_replace(' ', '-', strtolower($seed));
    $url = str_replace('\'s', '', $url);

    // remove unwarranted characters and squeeze dashes
    $url = preg_replace('/[^_a-z0-9-]/', '', $url);
    $url = preg_replace('/-+/', '-', $url);
    return $url;
  }

  private function compressJavascript($str) {
    $str = preg_replace('/^\s+/', '', $str);
    $str = preg_replace('/\s+$/', '', $str);
    return $str;
  }

  private function compressCSS($str) {
    $str = preg_replace('/\s+/', ' ', $str);
    $str = preg_replace('/\s*([{};:])\s*/', '\1', $str);
    $str = str_replace('}', "}\n", $str);
    return $str;
  }

  private function compressPNG($filename) {
    if (($img = @imagecreatefrompng($filename)) === false)
      throw new InvalidArgumentException("Unable to create image from file $filename.");
    $r = @imagepng($img, $filename, 9, PNG_ALL_FILTERS);
    if ($r === false)
      throw new InvalidArgumentException("Unable to create compressed PNG $filename.");
  }
}
?>