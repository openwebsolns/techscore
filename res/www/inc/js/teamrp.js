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

var old = window.onload;
window.onload = function(evt) {
    if (old)
        old(evt);
    if (initRP())
        checkRP();
};
