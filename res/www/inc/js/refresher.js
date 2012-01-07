/**
 * Script to check a page for a timestamp and refresh the page
 * accordingly
 *
 * @author Dayan Paez
 * @date   2010-03-03
 */

/**
 * Fetches the timestamp for the given document
 *
 * @return String the timestamp, or null
 */
function getTimestamp() {
    var metas = document.getElementsByTagName("meta");
    for (var i = 0; i < metas.length; i++) {
	if (metas[i].name == "timestamp")
	    return metas[i].content;
    }
    return null;
}

// from http://www.boutell.com/newfaq/creating/include.html

/**
 * Fetches the URL to look for time update
 *
 */
function getURL() {
    var url = document.location.href;
    url = url.substr(0, url.lastIndexOf("/")) + "/last-update";
    return url;
}

/**
 * Fetches the latest timestamp from URL
 *
 * @return String the timestamp, or null
 */
function getLatestTimestamp() {
    var url = getURL();
    var req = false;
    // For Safari, Firefox, and other non-MS browsers
    if (window.XMLHttpRequest) {
	try {
	    req = new XMLHttpRequest();
	} catch (e) {
	    req = false;
	}
    } else if (window.ActiveXObject) {
	// For Internet Explorer on Windows
	try {
	    req = new ActiveXObject("Msxml2.XMLHTTP");
	} catch (e) {
	    try {
		req = new ActiveXObject("Microsoft.XMLHTTP");
	    } catch (e) {
		req = false;
	    }
	}
    }
    if (req) {
	// Synchronous request, wait till we have it all
	req.open('GET', url, false);
	req.send(null);
	return req.responseText;
    }
    return null;
}


/**
 * Checks if a refresh is warranted and performs it
 *
 */
function checkRefresh() {
    var curr = getTimestamp();
    var last = getLatestTimestamp();

    if (curr && last) {
	if (last > curr) {
	    location.reload();
	}
    }
    else {
	// perform refresh anyways?
	// location.reload();
    }
}

var ivl = setInterval("checkRefresh()", 30000);

// remove the Refresh link
window.onload = function() {
    var elm = document.getElementById("menudiv");
    if (elm) {
	while (elm.hasChildNodes()) {
	    var node = elm.firstChild;
	    elm.removeChild(node);
	    if (node.nodeName == "DIV")
		break;
	}
    }
};