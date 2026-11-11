/* Make sure that all boats are unique prior to submitting */
/* Suitable for non-team racing                            */

(function(w, d) {
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
                SUBMIT_EXPL.appendChild(d.createTextNode("Not all sails have been provided."));
                return;
            }
            if (val in values) {
                SUBMIT_INPUT.disabled = true;
                SUBMIT_EXPL.appendChild(d.createTextNode("Duplicate sail " + val));
                return;
            }
            values[val] = val;
        }
    }

    w.addEventListener('load', function(evt) {
        var form = d.getElementById("rotation-form");
        var table = d.getElementById("sails-table");
        if (!form || !table)
            return;

        var inputs = table.getElementsByTagName("input");
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type == "text" && inputs[i].name != "name") {
                SAIL_INPUTS.push(inputs[i]);
                inputs[i].onchange = checkRotation;
            }
        }

        inputs = form.getElementsByTagName("input");
        for (i = inputs.length - 1; i >=0; i--) {
            if (inputs[i].type == "submit") {
                SUBMIT_INPUT = inputs[i];
                SUBMIT_EXPL = d.createElement("span");
                SUBMIT_EXPL.className = "message";
                SUBMIT_INPUT.parentNode.appendChild(SUBMIT_EXPL);
                break;
            }
        }

        var cf = function(select) {
            return function(e) {
                select.style.background = select.value;
            };
        };

        inputs = table.getElementsByTagName("select");
        for (i = 0; i < inputs.length; i++) {
            inputs[i].onchange = cf(inputs[i]);
        }

        checkRotation();
    }, false);
})(window, document);
