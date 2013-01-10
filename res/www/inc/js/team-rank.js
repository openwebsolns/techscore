/**
 * Visually handle re-ranking of teams, updating their win-loss record
 */

var RANK_TABLES = [];
var RANK_CELL_I = 0;

/**
 * Initializes the checkboxes event handlers
 *
 */
function initTeamRank(e) {
    var tabs = document.getElementsByClassName("rank-table");
    if (tabs.length == 0)
	return;

    for (var t = 0; t < tabs.length; t++) {
	var tab = tabs[t];
	RANK_TABLES.push(tab);
	var inputs = tab.getElementsByTagName("input");
	for (var i = 0; i < inputs.length; i++) {
	    if (inputs[i].type == "checkbox")
		inputs[i].onchange = updateRecord;
	}
    }

    // Add shuffle button
    var p = document.createElement("p");
    var b = document.createElement("button");
    p.appendChild(b);
    b.appendChild(document.createTextNode("Reorder teams"));
    b.setAttribute("type", "button");
    b.onclick = function(e) {
	RANK_TABLES.sort(compareRankTables);
	reorderTables();
    };
    tabs[0].parentNode.insertBefore(p, tabs[0]);
}

function updateRecord(e) {
    var td = this.parentNode.parentNode.childNodes[RANK_CELL_I];
    var tokens = td.childNodes[0].nodeValue.split("-");

    var index = 2;
    if (this.classList.contains("rank-win"))
	index = 0;
    else if (this.classList.contains("rank-lose"))
	index = 1;

    if (this.checked)
	tokens[index] = Number(tokens[index]) + 1;
    else
	tokens[index] = Number(tokens[index]) - 1;
    td.replaceChild(document.createTextNode(tokens.join("-")), td.childNodes[0]);
}

function compareRankTables(a, b) {
    var tok1 = a.childNodes[0].childNodes[RANK_CELL_I].childNodes[0].nodeValue.split("-");
    var tok2 = b.childNodes[0].childNodes[RANK_CELL_I].childNodes[0].nodeValue.split("-");

    var win1 = Number(tok1[0]);
    var win2 = Number(tok2[0]);

    var los1 = Number(tok1[1]);
    var los2 = Number(tok2[1]);

    var tot1 = win1 + los1;
    if (tok1.length > 2) { tot1 += Number(tok1[2]); }
    var tot2 = win2 + los2;
    if (tok2.length > 2) { tot2 += Number(tok2[2]); }

    var per1 = win1 / tot1;
    var per2 = win2 / tot2;

    if (per1 == per2) {
	if (win1 == win2)
	    return los1 - los2;
	return win2 - win1;
    }
    return per2 - per1;
}

/**
 * Visually update the tables according to order in RANK_TABLES
 *
 */
function reorderTables() {
    // Pick last, and insert others before it
    var last = RANK_TABLES[RANK_TABLES.length - 1];
    var parent = last.parentNode;
    for (var i = 0; i < RANK_TABLES.length - 1; i++) {
	parent.insertBefore(RANK_TABLES[i], last);
    }
}

if (window.onload) {
    var old = window.onload;
    window.onload = function(evt) {
	old();
	initTeamRank();
    };
}
else
    window.onload = initTeamRank;
