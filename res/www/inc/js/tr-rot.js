/* Make sure that all boats are unique prior to submitting */

var SAIL_INPUTS = Array();
var SUBMIT_INPUT = null;
var SUBMIT_EXPL = null;

function checkRotation() {
    SUBMIT_INPUT.disabled = false;
    while (SUBMIT_EXPL.childNodes.length > 0)
        SUBMIT_EXPL.removeChild(SUBMIT_EXPL.childNodes[0]);

    var values = {};
    for (var i = 0; i < SAIL_INPUTS.length; i++) {
        var val = SAIL_INPUTS[i].value.trim();
        if (val.length == 0) {
            SUBMIT_INPUT.disabled = true;
            SUBMIT_EXPL.appendChild(document.createTextNode("Not all sails have been provided."));
            return;
        }
        if (val in values) {
            SUBMIT_INPUT.disabled = true;
            SUBMIT_EXPL.appendChild(document.createTextNode("Duplicate sail " + value));
            return;
        }
        values[val] = val;
    }
}


var old = window.onload;
window.onload = function(evt) {
    if (old)
        old(evt);

    var inputs = document.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type == "text") {
            SAIL_INPUTS.push(inputs[i]);
            inputs[i].onchange = checkRotation;
        }
        else if (inputs[i].type == "submit") {
            SUBMIT_INPUT = inputs[i];
            SUBMIT_EXPL = document.createElement("span");
            SUBMIT_EXPL.className = "message";
            SUBMIT_INPUT.parentNode.appendChild(SUBMIT_EXPL);
        }
    }
    checkRotation();
};
