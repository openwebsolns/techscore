<?php
use \ui\HttpResponse;
use \users\AbstractUserPane;

/**
 * Echoes back the Techscore logo, while tracking for read tokens.
 *
 * This pane is only available in GET. Its main function is to return
 * the Techscore logo, which is linked to this page in e-mail
 * messages. Attached to the URL is a read_token, which will be then
 * be used to mark the corresponding Message as read.
 *
 * @author Dayan Paez
 * @created 2014-12-14
 */
class LogoPane extends AbstractUserPane {

  const LOGO_PATH = '/inc/img/techscore.png';

  public function __construct() {
    parent::__construct("Logo");
  }

  /**
   * Overrides the parent's method to return logo
   *
   */
  public function processGET(Array $args): HttpResponse {

    // Is there a token attached?
    if (isset($args['q'])) {
      $messages = DB::getMessagesWithReadToken((string)$args['q']);
      foreach ($messages as $message) {
        $message->read_time = DB::T(DB::NOW);
        DB::set($message);
      }
    }

    return HttpResponse::seeOther(self::LOGO_PATH);
  }

  public function fillHTML(Array $args) {}

  public function processPOST(Array $args): HttpResponse {
    return HttpResponse::notFound();
  }

  public function process(Array $args) {}
}
