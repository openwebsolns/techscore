/*  Javascript goodness tailored to team regattas */

var RPSUBMIT = null;
var RPEXPL = null;
var RPSAILORS = Array();
var RPRACES = Array();

/**
 * Initializes the variables needed to checkRP
 *
 * @return true if everything initialized correctly
 */
function initRP() {
    RPSUBMIT = document.getElementById('rpsubmit');
    if (!RPSUBMIT)
        return false;

    RPEXPL = document.createElement("span");
    RPEXPL.setAttribute("class", "message");
    RPSUBMIT.parentNode.appendChild(RPEXPL);

    var form = document.getElementById('rp-form');
    if (!form)
        return false;
    RPSAILORS = form.getElementsByTagName("select");
    for (var i = 0; i < RPSAILORS.length; i++) {
        RPSAILORS[i].onchange = checkRP;
    }

    var inputs = form.getElementsByTagName("input");
    for (i = 0; i < inputs.length; i ++) {
        if (inputs[i].type == "checkbox") {
            RPRACES.push(inputs[i]);
            inputs[i].onchange = checkRP;
        }
    }

    // also create buttons to load the team
    var tables = document.getElementsByTagName("table");
    for (i = 0; i < tables.length; i++) {
        if (tables[i].classList.contains("tr-rp-roundtable")) {
            var sets = {};
            var num_sets = 0;
            var rows = tables[i].getElementsByTagName("tr");
            for (var r = 1; r  < rows.length; r++) {
                for (var c = 0; c < rows[r].childNodes.length; c++) {
                    if (!(c in sets)) {
                        sets[c] = {};
                        num_sets++;
                    }
                    var spans = rows[r].childNodes[c].getElementsByTagName("span");
                    for (var s = 0; s < spans.length; s++) {
                        sets[c][spans[s].className] = spans[s].childNodes[0].nodeValue;
                    }
                }
            }
            // Add row to table
            var clickFactory = function(dict) {
                return function(evt) {
                    for (var i = 0; i < RPSAILORS.length; i++) {
                        if (RPSAILORS[i].name in dict) {
                            var sailor = dict[RPSAILORS[i].name];
                            for (var j = 0; j < RPSAILORS[i].length; j++) {
                                var opt = RPSAILORS[i].options.item(j);
                                if (opt.childNodes.length > 0 && opt.childNodes[0].nodeValue == sailor) {
                                    RPSAILORS[i].selectedIndex = j;
				    RPSAILORS[i].dispatchEvent(new Event('change'));
                                    break;
                                }
                            }
                        }
                        else
                            RPSAILORS[i].selectedIndex = 0;
                    }
                };
            };

            var row = document.createElement("tr");
            tables[i].childNodes[0].appendChild(row);
            for (c = 0; c < num_sets; c++) {
                var td = document.createElement("td");
                row.appendChild(td);

                if (Object.keys(sets[c]).length > 0) {
                    var bt = document.createElement("button");
                    bt.setAttribute("type", "button");
                    bt.onclick = clickFactory(sets[c]);
                    bt.appendChild(document.createTextNode("Load group"));
                    td.appendChild(bt);
                }
            }
        }
    }
    
    return true;
}

/**
 * Checks that races have been selected, and that sailors chosen are consistent.
 *
 * Updates the status bar and submit button
 *
 */
function checkRP() {
    RPSUBMIT.disabled = false;
    while (RPEXPL.childNodes.length > 0)
        RPEXPL.removeChild(RPEXPL.childNodes[0]);

    // Check that all sailors are different
    var chosen = Array();
    for (var i = 0; i < RPSAILORS.length; i++) {
        var value = RPSAILORS[i].value;
        if (value.trim() == "")
            continue;

        if (chosen.indexOf(value) >= 0) {
            invalidateSubmitRP("Repeated sailor chosen.");
            return;
        }
        chosen.push(value);
    }
    var hasRaces = false;
    for (i = 0; i < RPRACES.length; i++) {
        if (RPRACES[i].checked) {
            hasRaces = true;
            break;
        }
    }
    if (!hasRaces)
        invalidateSubmitRP("No races have been selected.");

    // Is warning on submission required?
    RPSUBMIT.onclick = null;
    if (chosen.length == 0)
        RPSUBMIT.onclick = function(evt) {
            return confirm("Submitting the form with no sailors will reset\nRP entries for chosen races.\n\nIs this the intended action?");
        };
}

function invalidateSubmitRP(expl) {
    RPSUBMIT.disabled = true;
    RPEXPL.appendChild(document.createTextNode(expl));
}

window.addEventListener('load', function(evt) {
    if (initRP())
        checkRP();
}, false);
