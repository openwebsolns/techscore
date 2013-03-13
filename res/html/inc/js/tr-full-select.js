/*
 * Narrow down team's display based on URL
 */

var TEAM_ROW_MAP = {};
var TEAM_PORT_MAP = {}; // map of ports to "hide"
var IS_SHOWING = false;

function extractTeamID(row) {
    for (var i = 0; i < row.classList.length; i++) {
        if (/team-[0-9]+/.test(row.classList.item(i)))
            return row.classList.item(i);
    }
    return null;
}

function filterTeamName(name) {
    var row;
    for (var i = 0; i < TEAM_ROW_MAP[name].length; i++) {
        row = TEAM_ROW_MAP[name][i];
        var j = 0;
        if (row.parentNode.nodeName.toLowerCase() == "table")
            j = 1;
        for (; j < row.parentNode.childNodes.length; j++) {
            if (row.parentNode.childNodes[j] != row)
                row.parentNode.childNodes[j].style.display = "none";
        }
    }
    for (var i = 0; i < TEAM_PORT_MAP[name].length; i++) {
        TEAM_PORT_MAP[name][i].style.display = "none";
    }
    IS_SHOWING = true;
}

function showAll() {
    for (var name in TEAM_ROW_MAP) {
        var row;
        for (var i = 0; i < TEAM_ROW_MAP[name].length; i++) {
            row = TEAM_ROW_MAP[name][i];
            for (var j = 0; j < row.parentNode.childNodes.length; j++) {
                row.parentNode.childNodes[j].style.display = "table-row";
            }
        }
    }
    for (var i = 0; i < TEAM_PORT_MAP[name].length; i++) {
        TEAM_PORT_MAP[name][i].style.display = "block";
    }
    window.location.hash = null;
    IS_SHOWING = false;
}

function initFullSelect() {
    var tables = document.getElementsByTagName("table");
    for (var t = 0; t < tables.length; t++) {
        var r, n, c, href;

        var funcFactory = function(n) {
            return function() {
                if (!IS_SHOWING) {
                    filterTeamName(n);
                    return true;
                }
                showAll();
                return false;
            }; };

        if (tables[t].classList.contains("teamranking")) {
            // Add message before
            var p = tables[t].previousSibling;
            if (!p || p.nodeName.toLowerCase() != "p") {
                p = document.createElement("p");
                tables[t].parentNode.insertBefore(p, tables[t]);
            }
            var st = document.createElement("strong");
            p.appendChild(st);
            st.appendChild(document.createTextNode(" Tip: click on team's record to toggle filter."));

            var rows = tables[t].childNodes[1].childNodes;
            for (r = 0; r < rows.length; r++) {

                n = extractTeamID(rows[r]);
                if (n) {
                    TEAM_ROW_MAP[n] = [rows[r]];
                    TEAM_PORT_MAP[n] = [];

                    c = rows[r].childNodes[5];
                    href = document.createElement("a");
                    href.setAttribute("href", "#" + n);
                    href.setAttribute("title", "Click to filter list");
                    href.onclick = funcFactory(n);
                    while (c.childNodes.length > 0)
                        href.appendChild(c.childNodes[0]);
                    c.appendChild(href);
                }
            }

        }
        else if (tables[t].classList.contains("teamscores")) {
            var indices = {};
            for (n in TEAM_PORT_MAP)
                indices[n] = TEAM_PORT_MAP[n].push(tables[t].parentNode);

            for (r = 1; r < tables[t].childNodes.length; r++) {
                n = extractTeamID(tables[t].childNodes[r]);
                if (n in TEAM_ROW_MAP) {
                    TEAM_ROW_MAP[n].push(tables[t].childNodes[r]);
                    TEAM_PORT_MAP[n] = TEAM_PORT_MAP[n].splice(indices[n], 1);

                    c = tables[t].childNodes[r].childNodes[1];
                    href = document.createElement("a");
                    href.setAttribute("href", "#" + n);
                    href.setAttribute("title", "Click to filter list");
                    href.onclick = funcFactory(n);
                    while (c.childNodes.length > 0)
                        href.appendChild(c.childNodes[0]);
                    c.appendChild(href);
                }
            }
        }
    }

    // Parse based on location
    var hash = window.location.hash;
    if (hash.length > 1) {
        hash = decodeURIComponent(hash.substring(1)).replace(/\+/g, " ");
        if (hash in TEAM_ROW_MAP) {
            filterTeamName(hash);
        }
    }
}

var old = window.onload;
if (old) {
    window.onload = function(evt) {
        old(evt);
        initFullSelect();
    };
}
else
    window.onload = initFullSelect;
