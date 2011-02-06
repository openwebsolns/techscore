/**
 * Checks for a fresh copy of the page every 60 seconds and loads it
 *
 * @author Dayan Paez
 * @version 2011-02-05
 */

CHECKER = null;

function checkVersion() {
    if (window.XMLHttpRequest){
	// code for IE7+, Firefox, Chrome, Opera, Safari
	xmlhttp = new XMLHttpRequest();
    }
    else {
	// Browser no good
	clearInterval(CHECKER);
	return;
    }
    xmlhttp.open("GET", document.location, false);
    xmlhttp.send();
    var s_copy = new Date(xmlhttp.getResponseHeader('Last-Modified'));
    if (s_copy > CURRENT)
	window.location.reload();
}
CURRENT = new Date(document.lastModified);
CHECKER = setInterval('checkVersion()', 60000);
