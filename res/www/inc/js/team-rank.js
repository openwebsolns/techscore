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

if (window.onload) {
    var old = window.onload;
    window.onload = function(evt) {
	old();
	initTeamRank();
    };
}
else
    window.onload = initTeamRank;
