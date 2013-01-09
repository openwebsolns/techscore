/**
 * Visually handle re-ranking of teams, updating their win-loss record
 */

var RANK_TABLES = [];

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
	for (var i = 0; i < inputs.length; i++)
	    inputs[i].onchange = updateRecord;
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
    var td = this.parentNode.parentNode.childNodes[0]; // first cell
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
    var tok1 = a.childNodes[0].childNodes[0].childNodes[0].nodeValue.split("-");
    var tok2 = b.childNodes[0].childNodes[0].childNodes[0].nodeValue.split("-");

    var rec1 = Number(tok1[0]);
    var rec2 = Number(tok2[0]);

    var tot1 = rec1 + Number(tok1[1]);
    if (tok1.length > 2) { tot1 += Number(tok1[2]); }
    var tot2 = rec2 + Number(tok2[1]);
    if (tok2.length > 2) { tot2 += Number(tok2[2]); }

    var per1 = rec1 / tot1;
    var per2 = rec2 / tot2;

    if (per1 == per2)
	return tok1[1] - tok2[1];
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
