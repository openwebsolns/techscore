/**
 * Collapses the different view panes in the report
 *
 * @author Dayan Paez
 * @version 2010-09-07
 */

PORTS = Array();

/**
 * Collapse
 */
function collapse() {
    var div = document.getElementById("menudiv");
    var all_divs = document.getElementsByTagName("div");
    var ports = Array();
    for (i = 0; i < all_divs.length; i++) {
	var cnam = all_divs[i].getAttribute("class");
	if (cnam != null && cnam.indexOf("port") >= 0) {
	    all_divs[i].setAttribute("id", i);
	    head = all_divs[i].getElementsByTagName("h3");
	    link = document.createElement("a");
	    link.setAttribute("onclick", "displayPort('" + i + "')");
	    link.setAttribute("href", "#" + i); // head[0].innerHTML);
	    link.appendChild(document.createTextNode(head[0].innerHTML));
	    div.appendChild(link);
	    
	    PORTS.push(all_divs[i]);
	}
    }
    for (i = 0; i < PORTS.length; i++)
	PORTS[i].setAttribute("style", "display: none;");
}

/**
 * Remove the hidden style attribute
 */
function displayPort(id) {
    for (i = 0; i < PORTS.length; i++) {
	if (PORTS[i].getAttribute("id") == id)
	    PORTS[i].removeAttribute("style");
	else
	    PORTS[i].setAttribute("style", "display: none;");
    }
}