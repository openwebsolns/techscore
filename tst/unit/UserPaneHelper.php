<?php
use \users\AbstractUserPane;

/**
 * Provides some utility functions for unit tests that deal with
 * panes.
 *
 * I know what you're thinking: who's unit testing this class?
 *
 * @author Dayan Paez
 * @version 2015-12-02
 */
class UserPaneHelper {

  public function getPaneHtml(AbstractUserPane $pane, Array $args) {
    ob_start();
    $pane->processGET($args);
    $text = ob_get_contents();
    ob_end_clean();
    return new SimpleXMLElement($text);
  }

  public function getPortTitle(SimpleXMLElement $port) {
    $this->autoregisterXpathNamespace($port);
    $h3s = $port->xpath('html:h3');
    if (count($h3s) == 0) {
      throw new InvalidArgumentException("Given port does not have an H3 element (title).");
    }
    return (string) $h3s[0];
  }

  public function autoregisterXpathNamespace(SimpleXMLElement $element, $prefix = 'html') {
    $namespaces = $element->getNamespaces();
    $element->registerXPathNamespace($prefix, array_shift($namespaces));
  }

}