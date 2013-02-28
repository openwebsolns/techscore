/*
 * Provide drop down for team-racing rotation display to
 * quickly show  only a specific team's races.
 */

var ROTATION_TABLE = null;
var TEAM_ROWS = Array();
var TEAM_ROW_MAP = {};
var TEAM_SELECT = null;

function extractTeamName(cell) {
    var txt = "";
    for (var i = 0; i < cell.childNodes.length; i++) {
        if (i > 0)
            txt += " ";
        if (cell.childNodes[i].nodeType == Node.TEXT_NODE)
            txt += cell.childNodes[i].nodeValue;
        else if (cell.childNodes[i].nodeType == Node.ELEMENT_NODE)
            txt += extractTeamName(cell.childNodes[i]);
    }
    return txt;
}

function showRotationForTeam(evt) {
    if (!TEAM_SELECT)
        return;
    
    var team = TEAM_SELECT.options[TEAM_SELECT.selectedIndex].value;
    var list = Array();
    if (team in TEAM_ROW_MAP)
        list = TEAM_ROW_MAP[team];

    // show all
    for (var i = 0; i < TEAM_ROWS.length; i++) {
        if (list.length == 0 || list.indexOf(TEAM_ROWS[i]) >= 0)
            TEAM_ROWS[i].style.display = "table-row";
        else
            TEAM_ROWS[i].style.display = "none";
    }
}

function initRotationSelect() {
    ROTATION_TABLE = document.getElementById("rotation-table");
    if (!ROTATION_TABLE)
        return;

    // Team names appear in columns 2 and 6
    var tbodies = ROTATION_TABLE.getElementsByTagName("tbody");
    for (var i = 0; i < tbodies.length; i++) {
        var rows = tbodies[i].getElementsByTagName("tr");
        // first row is roundrow
        for (var j = 1; j < rows.length; j++) {
            if (rows[j].childNodes.length < 7)
                continue;
            var team = extractTeamName(rows[j].childNodes[2]);
            if (!(team in TEAM_ROW_MAP))
                TEAM_ROW_MAP[team] = Array();
            TEAM_ROW_MAP[team].push(rows[j]);

            team = extractTeamName(rows[j].childNodes[6]);
            if (!(team in TEAM_ROW_MAP))
                TEAM_ROW_MAP[team] = Array();
            TEAM_ROW_MAP[team].push(rows[j]);

            TEAM_ROWS.push(rows[j]);
        }
    }

    // Create drop down box
    var p = document.createElement("p");
    p.setAttribute("id", "team-rotation-select");
    p.style.maxWidth = "25em";
    p.style.margin = "1em auto";

    p.appendChild(document.createTextNode("Show races for team: "));
    TEAM_SELECT = document.createElement("select");
    p.appendChild(TEAM_SELECT);
    TEAM_SELECT.onchange = showRotationForTeam;
    TEAM_SELECT.onkeyup = showRotationForTeam;

    var opt = document.createElement("option");
    opt.appendChild(document.createTextNode("[All teams]"));
    TEAM_SELECT.appendChild(opt);
    for (team in TEAM_ROW_MAP) {
        opt = document.createElement("option");
        opt.value = team;
        opt.appendChild(document.createTextNode(team));
        TEAM_SELECT.appendChild(opt);
    }

    ROTATION_TABLE.parentNode.insertBefore(p, ROTATION_TABLE);
}

var old = window.onload;
if (old) {
    window.onload = function(evt) {
        old(evt);
        initRotationSelect();
    };
}
else
    window.onload = initRotationSelect;
