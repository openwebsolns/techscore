/* Make sure that all boats are unique prior to submitting */
/* Choose sails 2 (and 3) when changing the first sail */

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

function changeOtherSails(master) {
    // TODO
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

    inputs = document.getElementsByTagName("select");
    var masterChangeFactory = function(master, slaves) {
        return function(evt) {
            master.style.background = master.value;
            for (var i = 0; i < slaves.length; i++) {
                slaves[i].value = master.value;
                slaves[i].style.background = master.value;
            }
        };
    };
    var slaveChangeFactory = function(sel) {
        return function(evt) {
            sel.style.background = sel.value;
        };
    };

    var masters = {"1" : null, "2": null};
    var slaves = {"1": [], "2": []};
    for (i = 0; i < inputs.length; i++) {
        var stub = inputs[i].name.substring(7, 8);
        var div = inputs[i].name.substring(5, 6);
        if (div == "A") {
            if (masters[stub] != null) {
                masters[stub].onchange = masterChangeFactory(masters[stub], slaves[stub]);
            }
            masters[stub] = inputs[i];
            slaves[stub] = [];
        }
        else {
            slaves[stub].push(inputs[i]);
            inputs[i].onchange = slaveChangeFactory(inputs[i]);
            inputs[i].classList.add("tr-slave");
        }
    }
    if (masters[stub] != null) {
        masters[stub].onchange = masterChangeFactory(masters[stub], slaves[stub]);
    }

    checkRotation();
};
