<?php
  //  Returns XML file with information about regatta
$whitelist = array("18.251.3.92",
		   "18.181.1.168");
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist))
  exit(1);

require_once '/var/local/ts/_elements.php';
require_once '/var/local/ts/_bg.php';
class XMLPage extends GenericElement
{
  public function __construct($root,
			      $child = array(),
			      $attrs = array()) {
    parent::__construct($root, $child, $attrs);
  }

  public function toHTML($ind = 0) {
    $str = '<?xml version="1.0"?>';
    $str .= parent::toHTML(0);
    return $str;
  }
}

if (!isset($_REQUEST['reg']) ||
    !is_numeric($_REQUEST['reg']))
  exit(1);

makeDBConnection();
$id = mysql_real_escape_string($_REQUEST['reg']);
$details = getRegattaDetails($id);
if (empty($details))
  exit(2);
if ($details['type'] == "personal")
  exit(4);

$PAGE = new XMLPage("regatta");
foreach ($details as $key => $value)
  $PAGE->addChild(new GenericElement($key,
				     array(new Text(stripslashes($value)
						    ))));

header("Content-type: text/xml");
echo $PAGE->toHTML();
?>