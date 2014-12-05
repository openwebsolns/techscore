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
 if (a){g.async=true;g.defer=true;}
 m.parentNode.insertBefore(g,m);
};';

      // Add JS files
      // For now, assume they're all ASYNC
      foreach (DB::getFilesLike('%.js') as $file) {
        if ($file->options === null)
          continue;

        $async = 'false';
        if (in_array(Pub_File::AUTOLOAD_ASYNC, $file->options))
          $async = 'true';
        elseif (!in_array(Pub_File::AUTOLOAD_SYNC, $file->options))
          continue;

        $this->filedata .= sprintf('
f("/inc/js/%s",%s);', $file, $async);
      }

      // Google search
      if (DB::g(STN::GCSE_ID) !== null) {
        $this->filedata .= sprintf('
f("%s",%s);', sprintf('//www.google.com/cse/cse.js?cx=%s', DB::g(STN::GCSE_ID)), 'true');
      }

      // Google Analytics code
      $this->filedata .= DB::g(STN::GOOGLE_ANALYTICS);

      // UserVoice
      if (DB::g(STN::USERVOICE_ID) !== null && DB::g(STN::USERVOICE_FORUM) !== null) {
        $this->filedata .= sprintf('
f("%s",%s);', sprintf('//widget.uservoice.com/%s.js', DB::g(STN::USERVOICE_ID)), 'true');
        $this->filedata .= sprintf('
UserVoice = window.UserVoice || [];
UserVoice.push(["showTab", "classic_widget", {
  mode: "feedback",
  primary_color: "#6C6D6F",
  link_color: "#3465a4",
  forum_id: %d,
  tab_label: "Feedback",
  tab_color: "#6c6d6f",
  tab_position: "bottom-left",
  tab_inverted: true
}]);
',
                                   DB::g(STN::USERVOICE_FORUM));
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