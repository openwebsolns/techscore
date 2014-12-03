<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

/**
 * Javascript file to load other javascript files
 *
 * This file allows us to create a more dynamic experience in a
 * static-site environment, without having to regenerate the entire
 * site.
 *
 * @author Dayan Paez
 * @created 2014-12-02
 */
class InitJs implements Writeable {

  private $filedata;

  private function getFiledata() {
    if ($this->filedata == null) {
      $this->filedata = '(function(w,d,s){';

      // Function to dynamically add other scripts:
      // Called as f(URL, ASYNC?)
      $this->filedata .= '
var m=d.getElementsByTagName(s)[0];
var f=function(u,a){
 var g=d.createElement(s);
 g.src=u;
 g.type="text/javascript";
 if (a!=undefined){g.async=true;g.defer=true;}
 m.parentNode.insertBefore(g,m);
};';

      // Add JS files
      // For now, assume they're all ASYNC
      foreach (DB::getFilesLike('%.js') as $file) {
        $this->filedata .= sprintf('
f("/inc/js/%s",true);', $file);
      }

      $this->filedata .= '
})(window,document,"script");';
    }
    return $this->filedata;
  }

  /**
   * Implementation of Writeable
   *
   */
  public function write($resource) {
    fwrite($resource, $this->getFiledata());
  }
}
?>