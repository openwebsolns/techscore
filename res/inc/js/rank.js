/*
 * Re-ranks teams based on division, bubble sorting
 *
 * @author Dayan Paez
 * @version 2011-03-17
 */

var TABLE;
var DIVS = {};
window.onload = function() {
    TABLE = document.getElementsByTagName('table')[0];
    var div = document.createElement('div');
    TABLE.parentNode.insertBefore(div, TABLE);
    div.setAttribute('id', 'rank-filter');
    div.appendChild(document.createTextNode('Order by: '));
    
    addInput(div, 'All');

    // Get divs
    var row = TABLE.getElementsByTagName('thead')[0].getElementsByTagName('tr')[0];
    for (var i = 0; i < row.childNodes.length; i++) {
	if (row.childNodes[i].innerHTML == 'A' ||
	    row.childNodes[i].innerHTML == 'B' ||
	    row.childNodes[i].innerHTML == 'C' ||
	    row.childNodes[i].innerHTML == 'D') {

	    DIVS[row.childNodes[i].innerHTML] = i;
	    addInput(div, row.childNodes[i].innerHTML);
	}
    }
};

function addInput(div, value) {
    var elem = document.createElement('input');
    elem.setAttribute('name', 'rank');
    elem.setAttribute('value', value);
    elem.setAttribute('id', value);
    elem.setAttribute('type', 'radio');
    elem.onclick = doRank;
    div.appendChild(elem);

    var label = document.createElement('label');
    label.setAttribute('for', value);
    label.appendChild(document.createTextNode(value));
    div.appendChild(label);
}

function doRank(evt) {
    value = evt.target.getAttribute('value');
    // display all cells
    var cells = TABLE.getElementsByTagName('td');
    for (var i = 0; i < cells.length; i++)
	cells[i].style.display = 'table-cell';
    var cells = TABLE.getElementsByTagName('th');
    for (var i = 0; i < cells.length; i++)
	cells[i].style.display = 'table-cell';

    ROWS = TABLE.getElementsByTagName('tbody')[0].childNodes;
    if (value != 'All') {
	// remove the i-th entry from each row
	var row = TABLE.getElementsByTagName('thead')[0].getElementsByTagName('tr')[0];
	for (var i = 4; i < row.childNodes.length; i++) {
	    if (DIVS[value] != i && DIVS[value] + 1 != i)
		row.childNodes[i].style.display = 'none';
	}
	RANK = Array();
	
	for (var r = 0; r < ROWS.length; r++) {
	    row = ROWS[r];
	    for (var i = 4; i < row.childNodes.length; i++) {
		if (DIVS[value] == i) {
		    var rank = row.childNodes[i].getAttribute('title').substring(15);
		    var exp = rank.indexOf('(');
		    if (exp > 0)
			rank = rank.substring(0, exp);
		    RANK.push(Number(rank));
		}
		else if (DIVS[value] + 1 != i)
		    row.childNodes[i].style.display = 'none';
	    }
	}

	// Bubble sort!
	for (var cycle = 0; cycle < RANK.length - 1; cycle++) {
	    var swapped = false;
	    for (var i = 0; i < RANK.length - 1; i++) {
		if (RANK[i] > RANK[i + 1]) {
		    swapped = true;
		    ROWS[i].parentNode.insertBefore(ROWS[i + 1], ROWS[i]);
		    var temp = RANK[i];
		    RANK[i] = RANK[i + 1];
		    RANK[i + 1] = temp;
		}
	    }
	    if (!swapped)
		break;
	}
    }
    else {
	// re-bubble sort, according to first cell
	// Bubble sort!
	for (var cycle = 0; cycle < ROWS.length - 1; cycle++) {
	    var swapped = false;
	    for (var i = 0; i < ROWS.length - 1; i++) {
		var rank1 = Number(ROWS[i].childNodes[0].innerHTML);
		var rank2 = Number(ROWS[i + 1].childNodes[0].innerHTML);
		if (rank1 > rank2) {
		    swapped = true;
		    ROWS[i].parentNode.insertBefore(ROWS[i + 1], ROWS[i]);
		}
	    }
	    if (!swapped)
		break;
	}
    }
    // update attribute of labels
    for (i = 0; i < evt.target.parentNode.childNodes.length; i++) {
	var elem = evt.target.parentNode.childNodes[i];
	if (elem.nodeName == 'label') {
	    if (elem.getAttribute('for') == value)
		elem.setAttribute('class', 'selected');
	    else
		elem.removeAttribute('class');
	}
    }
}