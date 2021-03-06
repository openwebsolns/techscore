// Search for sailors using AJAX
//
// @author Dayan Paez
// @version 2011-05-06

SEARCH = null;
RESULTS = null;
INPUT = null;
ACTIVE = Array();
TIMEOUT = null;
REQUEST = null;

function initAaSearch() {
    SEARCH = document.getElementById('name-search');
    SEARCH.onkeyup = launchSearch;
    INPUT = document.getElementById('aa-input');
    RESULTS = document.createElement('ul');
    RESULTS.setAttribute('id', 'aa-results');
    var li = document.createElement('li');
    li.setAttribute('class', 'message');
    li.appendChild(document.createTextNode('No results found.'));
    RESULTS.appendChild(li);

    INPUT.parentNode.insertBefore(RESULTS, INPUT);

    // add instructions
    var p = document.createElement('p');
    p.appendChild(document.createTextNode('Search sailors by name in the box below. As you search, a list of possible matches will appear directly below. Click on a sailor to promote them for inclusion (move them to the second list). Click on a sailor in the second list to exclude them.'));
    SEARCH.parentNode.parentNode.insertBefore(p, SEARCH.parentNode);
}

function launchSearch(evt) {
    if (REQUEST !== null)
	REQUEST.abort();
    if (TIMEOUT != null)
	clearTimeout(TIMEOUT);
    TIMEOUT = setTimeout(doSearch, 200);
}

function doSearch(evt) {
    if (SEARCH.value.length < 5)
	return;

    var doc;
    REQUEST = new XMLHttpRequest();
    REQUEST.onreadystatechange = function() {
	if (REQUEST.readyState == 4 && REQUEST.status == 200)
	    fillResults(REQUEST.responseXML);
    };
    REQUEST.open("GET", '/search?q=' + escape(SEARCH.value), true);
    REQUEST.send();
}

function fillResults(doc) {
    var res = doc.getElementsByTagName('Sailor');
    // empty RESULTS
    while (RESULTS.childNodes.length > 0)
	RESULTS.removeChild(RESULTS.childNodes[0]);

    if (res.length == 0) {
	var li = document.createElement('li');
	li.setAttribute('class', 'message');
	li.appendChild(document.createTextNode('No results found.'));
	RESULTS.appendChild(li);
    }
    for (var i = 0; i < res.length; i++) {
	var li = document.createElement('li');
	RESULTS.appendChild(li);
	var fn = res[i].getElementsByTagName('FirstName')[0];
	var ln = res[i].getElementsByTagName('LastName')[0];
	var yr = res[i].getElementsByTagName('Year')[0];
	var sh = res[i].getElementsByTagName('School')[0];

	var inp = document.createElement('input');
	inp.setAttribute('type', 'hidden');
	// inp.setAttribute('name', 'sailor-search[]');
	inp.setAttribute('value', res[i].getAttribute('id'));
	li.appendChild(inp);
	var elm = document.createElement('strong');
	elm.appendChild(document.createTextNode(fn.childNodes[0].nodeValue + " " +
						ln.childNodes[0].nodeValue));
	li.appendChild(elm);
	li.appendChild(document.createTextNode(" " + yr.childNodes[0].nodeValue +
					       " (" + sh.childNodes[0].nodeValue + ")"));

	li.style.cursor = 'pointer';
	li.onclick = promote;
	elm.onclick = promote;
    }
}

function promote(evt) {
    var li = evt.target;
    if (li.nodeName.toLowerCase() == 'strong')
	li = li.parentNode;
    var id = li.childNodes[0].value;
    
    // is this sailor already in the list?
    for (var i = 0; i < INPUT.childNodes.length; i++) {
	if (id == INPUT.childNodes[i].childNodes[0].value)
	    return;
    }
    
    addToInput(li);
    return false;
}

function addToInput(old_li) {
    if (INPUT.childNodes.length == 1 &&
	INPUT.childNodes[0].getAttribute('class') == 'message')
	INPUT.removeChild(INPUT.childNodes[0]);

    var li = old_li.cloneNode(true);
    li.onclick = demote;
    li.childNodes[0].setAttribute('name', 'sailor[]');

    INPUT.appendChild(li);
}

function demote(evt) {
    var li = evt.target;
    if (li.nodeName.toLowerCase() == 'strong')
	li = li.parentNode;
    var id = li.childNodes[0].value;
    li.parentNode.removeChild(li);
    return false;
}

var old = window.onload;
if (old) {
    window.onload = function() {
        old();
        initAaSearch();
    };
}
else
    window.onload = initAaSearch;

