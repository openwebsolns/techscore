/* Add swap button to race order template rows */
/* Specially made for  Greg */

var old = window.onload;
window.onload = function(evt) {
    if (old)
        old(evt);

    // get tables
    var tables = document.getElementsByTagName("table");
    for (var i = 0; i < tables.length; i++) {
        if (tables[i].classList.contains("tr-order-race")) {
            var rows = tables[i].getElementsByTagName("tr");
            var eventFactory = function(row) {
                return function(evt) {
                    var inputs = row.getElementsByTagName("input");
                    var swap = inputs[0].value;
                    inputs[0].value = inputs[1].value;
                    inputs[1].value = swap;
                };
            };
            for (var r = 0; r < rows.length; r++) {
                var cell, but;
                if (rows[r].parentNode.nodeName.toLowerCase() == "thead") {
                    cell = document.createElement("th");
                    rows[r].insertBefore(cell, rows[r].childNodes[2]);
                }
                else {
                    cell = document.createElement("td");
                    but = document.createElement("button");
                    but.setAttribute("type", "button");
                    but.appendChild(document.createTextNode("↔"));
                    but.onclick = eventFactory(rows[r]);
                    cell.appendChild(but);
                    rows[r].insertBefore(cell, rows[r].childNodes[2]);
                }
            }
        }
    }
};
