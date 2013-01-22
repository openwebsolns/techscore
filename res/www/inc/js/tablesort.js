// Sort me some rows by drag and drop. Again.
ROWS = Array();
DRAG_FROM = null;
DRAG_TO = null;

window.onload = function() {
    var table = document.getElementById('divtable');
    if (!table)
        return;
    var rows = table.getElementsByTagName('tr');
    var totalcells = 0;
    for (var r = 0; r < rows.length; r++) {
	var row = rows[r];
	if (row.getAttribute('class') == 'sortable') {
	    ROWS.push(row);
	    var cells = row.getElementsByTagName('td');
            totalcells = cells.length;
	    for (var c = 0; c < cells.length; c++) {
		var cell = cells[c];
		if (cell.getAttribute('class') == 'drag') {
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
    if (cells.length < 4 || table.classList.contains("narrow")) {
        var span = document.createElement('span');
        table.parentNode.insertBefore(span, table.nextSibling);
        span.setAttribute('class', 'message');
        span.appendChild(document.createTextNode("← Drag to change order"));
    }
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
