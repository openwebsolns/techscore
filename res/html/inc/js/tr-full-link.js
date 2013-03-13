/*
 * Provide links to full scores, but only if JS is supported
 */

function extractTeamID(row) {
    for (var i = 0; i < row.classList.length; i++) {
        if (/team-[0-9]+/.test(row.classList.item(i)))
            return row.classList.item(i);
    }
    return null;
}

function initFullLink() {
    var tables = document.getElementsByTagName("table");
    for (var t = 0; t < tables.length; t++) {
        if (tables[t].classList.contains("teamranking")) {
            // Add message before
            var p = tables[t].previousSibling;
            if (!p || p.nodeName.toLowerCase() != "p") {
                p = document.createElement("p");
                tables[t].parentNode.insertBefore(p, tables[t]);
            }
            p.appendChild(document.createTextNode(" Tip: click on team's record to view races."));

            var rows = tables[t].childNodes[1].childNodes;
            var n, c, href;
            for (var r = 0; r < rows.length; r++) {

                n = extractTeamID(rows[r]);
                if (n) {
                    c = rows[r].childNodes[5];
                    href = document.createElement("a");
                    href.setAttribute("href", "full-scores/#" + n);
                    href.setAttribute("title", "Click to view races");
                    while (c.childNodes.length > 0)
                        href.appendChild(c.childNodes[0]);
                    c.appendChild(href);
                }
            }
        }
    }
}

var old = window.onload;
if (old) {
    window.onload = function(evt) {
        old(evt);
        initFullLink();
    };
}
else
    window.onload = initFullLink;
