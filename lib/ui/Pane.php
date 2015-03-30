<?php
namespace ui;

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
   */
  public function processGET(Array $args);

  /**
   * Processes the POST request.
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  public function processPOST(Array $args);
}