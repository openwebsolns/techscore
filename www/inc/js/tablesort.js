// Sort me some rows by drag and drop. Again.
var TABLE = null;
var ROWS = Array();
var DRAG_FROM = null;
var DRAG_TO = null;
var MESSAGE = null;

function initTableSort() {
    ROWS = Array();
    DRAG_FROM = null;
    DRAG_TO = null;

    var rows = TABLE.getElementsByTagName('tr');
    var totalcells = 0;
    for (var r = 0; r < rows.length; r++) {
	var row = rows[r];
	if (row.classList.contains('sortable')) {
	    ROWS.push(row);
	    var cells = row.getElementsByTagName('td');
            totalcells = cells.length;
	    for (var c = 0; c < cells.length; c++) {
		var cell = cells[c];
		if (cell.classList.contains('drag')) {
		    cell.style.cursor = 'n-resize';
		    cell.onmousedown = function(evt) {
			var target = getTargetRow(evt);
			DRAG_FROM = target;
			return false;
		    };
		}
	    }
	    row.onmouseover = function(evt) {
		var target = getTargetRow(evt);
		if (DRAG_FROM == null) return;
		if (DRAG_FROM == target) return;
		DRAG_TO = target;
		DRAG_TO.parentNode.insertBefore(DRAG_FROM, DRAG_TO);
		// change value of input field
		DRAG_TO.getElementsByTagName('input')[0].value = nodeIndex(DRAG_TO);
		DRAG_FROM.getElementsByTagName('input')[0].value = nodeIndex(DRAG_FROM);
	    };
	    row.onmouseout = function(evt) {
		if (DRAG_TO == getTargetRow(evt))
		    DRAG_TO = null;
	    };
	    // disable input
	    var input = row.getElementsByTagName('input')[0];
	    input.disabled = true;
	}
	// hide the first cell altogether
	row.childNodes[0].style.display = "none";
    }

    if (totalcells < 4 || TABLE.classList.contains("narrow")) {
        MESSAGE = document.createElement('span');
        TABLE.parentNode.insertBefore(MESSAGE, TABLE.nextSibling);
        MESSAGE.setAttribute('class', 'message');
        MESSAGE.appendChild(document.createTextNode("← Drag to change order"));
    }
}

function destroyTableSort() {
    if (MESSAGE)
        MESSAGE.parentNode.removeChild(MESSAGE);

    for (var r = 0; r < ROWS.length; r++) {
	var cells = ROWS[r].getElementsByTagName('td');
	for (var c = 0; c < cells.length; c++) {
	    var cell = cells[c];
	    if (cell.classList.contains('drag')) {
		cell.style.cursor = 'auto';
		cell.onmousedown = null;
	    }
	}
	ROWS[r].onmouseover = null;
	ROWS[r].onmouseout = null;

	var input = ROWS[r].getElementsByTagName('input')[0];
	input.disabled = false;
    }
    var rows = TABLE.getElementsByTagName("tr");
    // show the first cell altogether
    for (r = 0; r < rows.length; r++)
	rows[r].childNodes[0].style.display = "table-cell";
}

window.onload = function() {
    TABLE = document.getElementById('divtable');
    if (!TABLE)
        return;
    initTableSort();
};

window.onmouseup = function(evt) {
    DRAG_FROM = null;
};

function getTargetRow(e) {
    var targ;
    if (!e) var e = window.event;
    if (e.srcElement)
	targ = e.srcElement;
    else if (e.target)
	targ = e.target;
    if (targ.nodeType == 3) // defeat Safari bug
	targ = targ.parentNode;

    // climb up until a tr
    while (targ.nodeName.toLowerCase() != 'tr')
	targ = targ.parentNode;
    return targ;
}

function nodeIndex(elem) {
    for (var i = 0; i < elem.parentNode.childNodes.length; i++) {
	if (elem == elem.parentNode.childNodes[i])
	    return (i + 1);
    }
    return null;
}
