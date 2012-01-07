/*
 * Re-ranks teams based on division, bubble sorting
 *
 * @author Dayan Paez
 * @version 2011-03-17
 */

var TABLES = {};
var DIVS = {};
window.onload = function() {
    var table = document.getElementsByTagName('table')[0];
    var div = document.createElement('div');
    table.parentNode.insertBefore(div, table);
    div.setAttribute('id', 'rank-filter');
    div.appendChild(document.createTextNode('Order by: '));

    TABLES['All'] = table;
    addInput(div, 'All');

    // Get divs
    var row = table.getElementsByTagName('thead')[0].getElementsByTagName('tr')[0];
    for (var i = 0; i < row.childNodes.length; i++) {
	if (row.childNodes[i].innerHTML == 'A' ||
	    row.childNodes[i].innerHTML == 'B' ||
	    row.childNodes[i].innerHTML == 'C' ||
	    row.childNodes[i].innerHTML == 'D') {

	    DIVS[row.childNodes[i].innerHTML] = i;
	    addInput(div, row.childNodes[i].innerHTML);
	    addTable(row.childNodes[i].innerHTML);
	}
    }
};

function addInput(div, value) {
    var elem = document.createElement('input');
    elem.setAttribute('name', 'rank');
    elem.setAttribute('value', value);
    elem.setAttribute('id', value);
    elem.setAttribute('type', 'radio');
    elem.onclick = hideOthers;
    div.appendChild(elem);

    var label = document.createElement('label');
    label.setAttribute('for', value);
    label.appendChild(document.createTextNode(value));
    div.appendChild(label);
}

function addTable(value) {
    // Clone reference table
    var table = TABLES['All'].cloneNode(true);
    table.style.display = 'none';
    TABLES[value] = table;
    TABLES['All'].parentNode.appendChild(table);

    RANK = Array();
    // remove the i-th entry from each row
    var rows = table.getElementsByTagName('tr');
    for (var r = 0; r < rows.length; r++) {
	var row = rows[r];
	// remove all rows between index 4 and the div
	for (var i = 4; i < DIVS[value]; i++)
	    row.removeChild(row.childNodes[4]);
	if (row.childNodes[4].nodeName.toLowerCase() != 'th') {
	    // Keep rank of these for bubble sorting later, but
	    // only if they are not TH elements
	    var title = row.childNodes[4].getAttribute('title').substring(15);
	    var rank = title;
	    var exp  = "";
	    
	    var paren = title.indexOf('(');
	    if (paren > 0) {
		rank = title.substring(0, paren);
		exp  = title.substring(paren);
	    }
	    RANK.push(Number(rank));
	    row.childNodes[0].innerHTML = rank;
	    row.childNodes[0].setAttribute('title', exp);
	    row.setAttribute('id', 'r'+rank);
	}
	while (6 < row.childNodes.length)
	    row.removeChild(row.childNodes[6]);
    }

    // Bubble sort! (also need to update the class)
    var tbody = table.childNodes[1];
    for (var cycle = 0; cycle < RANK.length - 1; cycle++) {
	var swapped = false;
	for (var i = 0; i < RANK.length - 1; i++) {
	    if (RANK[i] > RANK[i + 1]) {
		swapped = true;
		tbody.insertBefore(tbody.childNodes[i + 1], tbody.childNodes[i]);
		
		var temp = RANK[i];
		RANK[i] = RANK[i + 1];
		RANK[i + 1] = temp;
	    }
	}
	if (!swapped)
	    break;
    }
}

function hideOthers(evt) {
    var value = evt.target.getAttribute('value');
    for (index in TABLES) {
	if (index != value)
	    TABLES[index].style.display = 'none';
	else
	    TABLES[index].style.removeProperty('display');
    }
    // update attribute of labels
    for (i = 0; i < evt.target.parentNode.childNodes.length; i++) {
	var elem = evt.target.parentNode.childNodes[i];
	if (elem.nodeName.toLowerCase() == 'label') {
	    if (elem.getAttribute('for') == value)
		elem.setAttribute('class', 'selected');
	    else
		elem.removeAttribute('class');
	}
    }
}
