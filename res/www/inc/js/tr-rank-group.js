/* Group teams into ranks */

// Drag and drop implementation
var DRAG_FROM = null;
var DRAG_TO = null;

var RANK_TABLE = null;
var RANK_GROUPS = {};
var RANK_GROUP_CELLS = {};
var RANK_GROUP_ORDER = [];

var RANK_SUBMIT_BUTTON = null;
var RANK_SUBMIT_EXPL = null;

function prependNewRankGroup() {
    // bump every existing group value by one
    var new_rank_group = {};
    var new_group_cells = {};
    for (var grp in RANK_GROUPS) {
        var next = String(Number(grp) + 1);
        new_rank_group[next] = RANK_GROUPS[grp];
        new_group_cells[next] = RANK_GROUP_CELLS[grp];

        for (var i = 0; i < new_rank_group[next].length; i++) {
            var input = new_rank_group[next][i].childNodes[0].childNodes[0];
            input.setAttribute("value", next);
        }
    }
    var new_group_order = [];
    for (var i = 0; i < RANK_GROUP_ORDER.length; i++)
        new_group_order.push(String(Number(RANK_GROUP_ORDER[i]) + 1));

    RANK_GROUPS = new_rank_group;
    RANK_GROUP_CELLS = new_group_cells;
    RANK_GROUP_ORDER = new_group_order;

    RANK_GROUPS["1"] = [];
    RANK_GROUP_ORDER.splice(0, 0, "1");

    var td = document.createElement("td");
    RANK_GROUP_CELLS["1"] = td;

    var row = document.createElement("tr");
    row.appendChild(td);
    td.setAttribute("class", "tr-js-grouplabel");
    td.setAttribute("colspan", 2);
    RANK_TABLE.childNodes[1].insertBefore(row, RANK_TABLE.childNodes[1].childNodes[1]);

    updateRankValues("1");
}

function appendNewRankGroup() {
    // find the last index
    var last = RANK_GROUP_ORDER[RANK_GROUP_ORDER.length - 1];
    var next = String(Number(last) + 1);

    RANK_GROUPS[next] = [];
    RANK_GROUP_ORDER.push(next);

    var td = document.createElement("td");
    RANK_GROUP_CELLS[next] = td;

    var row = document.createElement("tr");
    row.appendChild(td);
    td.setAttribute("class", "tr-js-grouplabel");
    td.setAttribute("colspan", 2);

    RANK_TABLE.childNodes[1].insertBefore(row,
                                          RANK_TABLE.childNodes[1].childNodes[RANK_TABLE.childNodes[1].childNodes.length - 2]);

    updateRankValues(next);
}

function updateRankValues(to_group) {
    var from_group = getRowRankGroup(DRAG_FROM);
    if (from_group == to_group)
        return;

    var last_item;
    if (RANK_GROUPS[to_group].length > 0)
        last_item = RANK_GROUPS[to_group][RANK_GROUPS[to_group].length - 1];
    else
        last_item = RANK_GROUP_CELLS[to_group].parentNode;
    last_item.parentNode.insertBefore(DRAG_FROM, last_item.nextSibling);
        
    var inputs = DRAG_FROM.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type == "text") {
            inputs[i].setAttribute("value", to_group);
            break;
        }
    }
    RANK_GROUPS[to_group].push(DRAG_FROM);
    RANK_GROUPS[from_group].splice(RANK_GROUPS[from_group].indexOf(DRAG_FROM), 1);
    if (RANK_GROUPS[from_group].length == 0) {
        var row = RANK_GROUP_CELLS[from_group].parentNode;
        row.parentNode.removeChild(row);
        delete(RANK_GROUP_CELLS[from_group]);
        delete(RANK_GROUPS[from_group]);
        RANK_GROUP_ORDER.splice(RANK_GROUP_ORDER.indexOf(from_group), 1);
    }
    updateCellDisplay();
}

function updateCellDisplay() {
    RANK_SUBMIT_BUTTON.disabled = false;
    while (RANK_SUBMIT_EXPL.childNodes.length > 0)
        RANK_SUBMIT_EXPL.removeChild(RANK_SUBMIT_EXPL.childNodes[0]);

    var valid = true;

    var min = 1;
    var max = null;
    var grp = null;
    for (var i = 0; i < RANK_GROUP_ORDER.length; i++) {
        grp = RANK_GROUP_ORDER[i];
        max = min + RANK_GROUPS[grp].length - 1;

        var str = min + "-" + max;
        if (max <= min) {
            str = min;
            valid = false;
        }
        while (RANK_GROUP_CELLS[grp].childNodes.length > 0)
            RANK_GROUP_CELLS[grp].removeChild(RANK_GROUP_CELLS[grp].childNodes[0]);
        RANK_GROUP_CELLS[grp].appendChild(document.createTextNode(str));

        min = max + 1;
    }

    if (!valid) {
        RANK_SUBMIT_BUTTON.disabled = true;
        RANK_SUBMIT_EXPL.appendChild(document.createTextNode("Each group must have at least 2 teams."));
    }
}

function initRankGroup() {
    RANK_TABLE = document.getElementById("tr-rankgroup-table");
    if (!RANK_TABLE)
        return;

    RANK_SUBMIT_BUTTON = document.getElementById("submit-input");
    if (!RANK_SUBMIT_BUTTON)
        return;
    RANK_SUBMIT_EXPL = document.createElement("span");
    RANK_SUBMIT_EXPL.classList.add("message");
    RANK_SUBMIT_BUTTON.parentNode.appendChild(RANK_SUBMIT_EXPL);

    // add explanation
    var p = document.createElement("p");
    p.appendChild(document.createTextNode("Drag a team from one rank group to another. The cell above the group indicates the range of ranks that will be used for the teams under it. To add a new group at the beginning or end, drag the team to the first and last cells of the table."));
    RANK_TABLE.parentNode.insertBefore(p, RANK_TABLE);

    // separate inputs into groups
    var ungrouped = [];
    var inputs = RANK_TABLE.getElementsByTagName("input");
    var max_val = 0;
    var val;
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type != "text")
            continue;

        val = inputs[i].value;
        if (val.length == 0)
            ungrouped.push(inputs[i].parentNode.parentNode);
        else {
            if (!(val in RANK_GROUPS))
                RANK_GROUPS[val] = [];
            RANK_GROUPS[val].push(inputs[i].parentNode.parentNode);
            if (Number(val) > max_val)
                max_val = Number(val);
        }
    }
    if (ungrouped.length > 0)
        RANK_GROUPS[max_val + 1] = ungrouped;

    var td, row, newRow;

    // delete first cell in each row add row before each group
    RANK_TABLE.childNodes[0].childNodes[0].childNodes[0].style.display = "none";
    var rows = RANK_TABLE.childNodes[1].childNodes;
    var prevValue = null;
    for (i = 0; i < rows.length; i++) {
        row = rows[i];
        row.childNodes[0].style.display = "none";

        for (var j = 1; j < row.childNodes.length; j++) {
            row.childNodes[j].style.cursor = "move";
            row.childNodes[j].onmousedown = function(evt) {
		var target = getTargetRow(evt);
		DRAG_FROM = target;
                DRAG_FROM.classList.add("js-moving");
		return false;
	    };
        }
        row.onmouseover = function(evt) {
	    var target = getTargetRow(evt);
	    if (DRAG_FROM == null || DRAG_FROM == target) return;
            if (DRAG_TO == target) return;
	    DRAG_TO = target;
            updateRankValues(getRowRankGroup(DRAG_TO));
	};
	row.onmouseout = function(evt) {
	    if (DRAG_TO == getTargetRow(evt))
		DRAG_TO = null;
	};

        val = row.childNodes[0].childNodes[0].value;
        if (prevValue == null || prevValue != val) {
            newRow = document.createElement("tr");
            td = document.createElement("td");
            td.className = "tr-js-grouplabel";
            td.setAttribute("colspan", 2);
            newRow.appendChild(td);

            row.parentNode.insertBefore(newRow, row);

            prevValue = val;
            RANK_GROUP_ORDER.push(val);
            RANK_GROUP_CELLS[val] = td;
        }
    }
    updateCellDisplay();

    // add a row at the top, and one at the bottom
    row = document.createElement("tr");
    RANK_TABLE.childNodes[1].insertBefore(row, RANK_TABLE.childNodes[1].childNodes[0]);
    td = document.createElement("td");
    td.className = "tr-js-drag";
    td.setAttribute("colspan", 3);
    td.appendChild(document.createTextNode("Drag here to create new group"));
    row.appendChild(td);
    row.onmouseover = function(evt) {
        if (DRAG_FROM == null) return;
        prependNewRankGroup();
    };

    row = row.cloneNode(true);
    row.onmouseover = function(evt) {
        if (DRAG_FROM == null) return;
        appendNewRankGroup();
    };
    RANK_TABLE.childNodes[1].appendChild(row);
    
}

var old = window.onload;
window.onload = function(evt) {
    if (old)
        old(evt);
    initRankGroup();
};


// DRAG and DROP functionality (tablesort.js)
window.onmouseup = function(evt) {
    if (DRAG_FROM != null) {
        DRAG_FROM.classList.remove("js-moving");
        DRAG_FROM = null;
    }
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

function getRowRankGroup(row) {
    for (var group in RANK_GROUPS) {
        if (RANK_GROUPS[group].indexOf(row) >= 0)
            return group;
    }
    return null;
}

function nodeIndex(elem) {
    for (var i = 0; i < elem.parentNode.childNodes.length; i++) {
	if (elem == elem.parentNode.childNodes[i])
	    return (i + 1);
    }
    return null;
}
