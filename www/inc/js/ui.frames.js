/*
 * Stacking frames
 *
 * @author Dayan Paez
 * @date   2010-03-24
 */

// LIST of FRAMES
var FRAMES;
var FRAMES_SRC;

/**
 * Initializes the page for handling frames
 *
 */
function init() {
    FRAMES = Array();
    FRAMES_SRC = Array();
    frames = getFrameAnchors();
    for (var i = 0; i < frames.length; i++) {
	FRAMES.push(0);
	FRAMES_SRC.push(frames[i].href);
	frames[i].href = 'javascript:toggleFrame(' + i + ')';
	frames[i].removeAttribute("target");
    }
}

/**
 * Returns list of anchors which open frames
 */
function getFrameAnchors() {
    var list = document.getElementsByTagName("a");
    var anc  = Array();
    for (var i = 0; i < list.length; i++) {
	if (list[i].className == "frame-toggle")
	    anc.push(list[i]);
    }
    return anc;
}

function toggleFrame(id) {
    var e = document.getElementById("frames" + id);
    if (e == null) {
	showFrame(id);
    }
    else {
	closeFrame(id);
    }
}

function closeFrame(id) {
    FRAMES[id] = 0;
    
    var e = document.getElementById("frames" + id);
    e.parentNode.removeChild(e);
    restack();
}

function showFrame(id) {
    FRAMES[id] = 1;

    var bd = document.getElementsByTagName ("body") [0];
    var div = document.createElement("div");
    div.setAttribute("id",  "frames" + id);
    div.setAttribute("class", "frames");
    bd.appendChild(div);
    
    var elem = document.createElement("iframe");
    elem.setAttribute("style", "height: 100%;");
    elem.setAttribute("src", FRAMES_SRC[id]);
    elem.appendChild(document.createTextNode("Your browser does not support iframes."));
    div.appendChild(elem);

    // close button
    elem = document.createElement("img");
    elem.setAttribute("src", "/img/error.png");
    elem.setAttribute("style", "cursor: pointer; position: absolute; top: 5px; left: 5px;");
    elem.setAttribute("onclick", "javascript:closeFrame(" + id + ")");
    elem.setAttribute("title", "Close dialog");
    div.appendChild(elem);

    // maximize
    // elem = document.createElement("span");
    elem = document.createElement("img");
    elem.setAttribute("onclick", "javascript:maximizeFrame('frames" + id + "')");
    // elem.appendChild(document.createTextNode("Maximize"));
    elem.setAttribute("src", "/img/max.png");
    elem.setAttribute("style", "cursor: nw-resize; position: absolute; top: 5px; left: 20px;");
    elem.setAttribute("alt", "Maximize");
    elem.setAttribute("title", "Maximize");
    div.appendChild(elem);

    // minimize
    // elem = document.createElement("span");
    elem = document.createElement("img");
    elem.setAttribute("style", "cursor: se-resize; position: absolute; top: 5px; left: 35px;");
    elem.setAttribute("onclick", "javascript:minimizeFrame('frames" + id + "')");
    // elem.appendChild(document.createTextNode("Minimize"));
    elem.setAttribute("src", "/img/min.png");
    elem.setAttribute("alt", "Minimize");
    elem.setAttribute("title", "Minimize");
    div.appendChild(elem);

    // Open in a new window
    // elem = document.createElement("span");
    elem = document.createElement("img");
    elem.setAttribute("style", "cursor: ne-resize; position: absolute; top: 5px; left: 50px;");
    elem.setAttribute("onclick", "javascript:breakFrame(" + id + ")");
    elem.setAttribute("src", "/img/break.png");
    elem.setAttribute("alt", "Break out");
    elem.setAttribute("title", "Open in a new window");
    // elem.appendChild(document.createTextNode("Break out"));
    div.appendChild(elem);

    restack();
}

function maximizeFrame(id) {
    var e = document.getElementById(id);
    e.removeAttribute("style");
    e.setAttribute("class", "maximized");
}

function minimizeFrame(id) {
    var e = document.getElementById(id);
    e.setAttribute("class", "frames");
    restack();
}

function restack() {
    var bd = document.getElementsByTagName("body")[0];

    // Get array of frames
    var frames = bd.getElementsByTagName("iframe");
    for (var i = 0; i < frames.length; i++) {
	var f = frames[i].parentNode;
	var height = 100 / frames.length;
	var top = i * height;
	f.setAttribute("style", "height: " + height + "%; top: " + top + "%;");
    }

    if (frames.length > 0)
	bd.setAttribute("style", "margin-left: 0px;");
    else
	bd.setAttribute("style", "margin-left: auto;");

    setCookie();
}

function breakFrame(id) {
    var f = document.getElementById("frames" + id).getElementsByTagName("iframe")[0];
    window.open(f.getAttribute("src"), "_blank", "toolbar=no, location=no, directories=no, status=no, menubar=yes, resizeable=yes, copyhistory=no");
    closeFrame(id);
}


// ------------------------------------------------------------
// COOKIES
// ------------------------------------------------------------
var COOKIE_NAME = "TSgui";

/**
 * Sets the cookie dealing with the GUI
 *
 */
function setCookie() {
    document.cookie = COOKIE_NAME + "=" + escape(FRAMES.join(","));
}

/**
 * Gets the cookie values
 *
 */
function loadCookie() {
    if (document.cookie.length > 0) {
	c_start = document.cookie.indexOf(COOKIE_NAME + "=");
	if (c_start!=-1) {
	    c_start = c_start + (COOKIE_NAME.length + 1);
	    c_end   = document.cookie.indexOf(";", c_start);
	    if (c_end == -1)
		c_end = document.cookie.length;
	    var params = unescape(document.cookie.substring(c_start,c_end));
	    var list   = params.split(",");
	    var i = 0;
	    while (i < FRAMES.length && i < list.length) {
		if (list[i] != null && Number(list[i]) > 0) {
		    FRAMES[i] = "1";
		    showFrame(i);
		}
		i++;
	    }
	}
    }
}

$(document).ready(function() {
	init();
	loadCookie();
    });