/* Make sure that all boats are unique prior to submitting */
/* Choose sails 2 (and 3) when changing the first sail */

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

    function updateRotationStyle(select) {
        select.style.background = select.value;
    }

    
    w.addEventListener('load', function(evt) {
        var form = d.getElementById("tr-rotation-form");
        if (!form)
            return;

	var inputs = d.getElementsByTagName("input");
	for (i = inputs.length - 1; i >= 0; i--) {
	    if (inputs[i].type == "submit") {
		SUBMIT_INPUT = inputs[i];
		SUBMIT_EXPL = d.createElement("span");
		SUBMIT_EXPL.className = "message";
		SUBMIT_INPUT.parentNode.appendChild(SUBMIT_EXPL);
		break;
	    }
	}

        var masterChangeFactory = function(master, slaves) {
            return function(evt) {
                updateRotationStyle(master);
                for (var i = 0; i < slaves.length; i++) {
                    slaves[i].value = master.value;
                    updateRotationStyle(slaves[i]);
                }
            };
        };
        var slaveChangeFactory = function(sel) {
            return function(evt) {
                updateRotationStyle(sel);
            };
        };

        var tables = form.getElementsByTagName("table");
        for (var i = 0; i < tables.length; i++) {
            if (tables[i].classList.contains("sail-list")) {
		inputs = tables[i].getElementsByTagName("input");
		for (var j = 0; j < inputs.length; j++) {
		    if (inputs[j].type == "text" && inputs[j].name != "name") {
			SAIL_INPUTS.push(inputs[j]);
			inputs[j].onchange = checkRotation;
		    }
		}

                inputs = tables[i].getElementsByTagName("select");
                if (inputs.length > 1) {
                    inputs[0].onchange = masterChangeFactory(inputs[0], inputs);
                }
                if (inputs.length > 0) {
                    updateRotationStyle(inputs[0]);
                }
                for (j = 1; j < inputs.length; j++) {
                    inputs[j].onchange = slaveChangeFactory(inputs[j]);
                    updateRotationStyle(inputs[j]);
                }
            }
        }

        checkRotation();
    }, false);
})(window, document);
