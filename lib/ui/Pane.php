<?php
namespace ui;

use \utils\HttpResponse;

/**
 * Interface for classes that can process POST and reply to GET.
 *
 * @author Dayan Paez
 * @version 2015-03-30
 */
interface Pane {

  /**
   * Processes the GET request, usually by printing HTML to stdout.
   *
   * @param Array $args the arguments to consider.
   * @return HttpResponse
   */
  public function processGET(Array $args): HttpResponse;

  /**
   * Processes the POST request.
   *
   * @param Array $args the parameters to process
   * @return HttpResponse with parameters to pass to the next page
   */
  public function processPOST(Array $args): HttpResponse;
}
