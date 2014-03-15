// Functions specific to entering scores
// Dayan Paez
// September 10, 2008

(function() {
var FINISH_INPUT = [];
var FINISH_CHECK = [];
var FINISH_OUTPUT = [];
var POSSIBLE_VALUES = [];
var SUBMIT_BUTTON = null;
var EMPTY_VALUE = "";
var EMPTY_CONTENT = null;
var ERROR_CONTENT = null;

function setCheckValue(i, newValue) {
    FINISH_CHECK[i].parentNode.replaceChild(newValue, FINISH_CHECK[i]);
    FINISH_CHECK[i] = newValue;
}

function validateSails() {
    var can_submit = true, i, j;

    // Reset inputs
    for (i = 0; i < FINISH_INPUT.length; i++) {
        FINISH_INPUT[i].style.visibility = "visible";
    }

    // Iterate over outputs
    for (i = 0; i < FINISH_OUTPUT.length; i++) {
        var value = FINISH_OUTPUT[i].value;
        if (value == EMPTY_VALUE) {
            setCheckValue(i, EMPTY_CONTENT.cloneNode());
            can_submit = false;
        }
        else {
            var checkContent = document.createTextNode(i + 1);

            // Does the value exist?
            j = POSSIBLE_VALUES.indexOf(value);
            if (j < 0) {
                can_submit = false;
                checkContent = ERROR_CONTENT.cloneNode();
                checkContent.setAttribute("title", "Invalid value provided.");
            }
            else {
                // Hide the value FINISH_INPUT
                FINISH_INPUT[j].style.visibility = "hidden";

                // Check against all previous others
                for (j = 0; j < i; j++) {
                    if (FINISH_OUTPUT[j].value == value) {
                        can_submit = false;
                        // Error: alert both!
                        checkContent = ERROR_CONTENT.cloneNode();
                        checkContent.setAttribute("title", "Error: duplicate of entry #" + (j + 1));

                        var otherContent = ERROR_CONTENT.cloneNode();
                        otherContent.setAttribute("title", "Error: duplicate of entry #" + (i + 1));
                        
                        setCheckValue(j, otherContent);
                        break;
                    }
                }
            }

            setCheckValue(i, checkContent);
        }
    }

    SUBMIT_BUTTON.disabled = !can_submit;
}

// Appends the passed value to next available spot in list
function appendToList(elem) {
    // Get all outputs
    for (var i = 0; i < FINISH_OUTPUT.length; i++) {
        if (FINISH_OUTPUT[i].value == EMPTY_VALUE) {
            FINISH_OUTPUT[i].value = elem;
            break;
        }
    }
}


window.addEventListener('load', function(e) {
    var table = document.getElementById("finish_table");
    if (!table)
        return;

    EMPTY_CONTENT = document.createElement("img");
    EMPTY_CONTENT.src = "/inc/img/question.png";
    EMPTY_CONTENT.alt = "?";
    EMPTY_CONTENT.setAttribute("title", "Waiting for input");

    ERROR_CONTENT = document.createElement("img");
    ERROR_CONTENT.src = "/inc/img/e.png";
    ERROR_CONTENT.alt = "X";

    SUBMIT_BUTTON = document.getElementById("submitfinish");
    SUBMIT_BUTTON.disabled = true;
    var form = document.getElementById("finish_form");
    if (form) {
        var he = document.createElement("input");
        he.type = "hidden";
        he.name = SUBMIT_BUTTON.name;
        he.value = SUBMIT_BUTTON.value;
        form.appendChild(he);
        SUBMIT_BUTTON.onclick = function(e) {
            SUBMIT_BUTTON.disabled = true;
            form.submit();
            return false;
        };
    }

    // Grab inputs
    var cf = function(sail) {
        return function(e) {
            appendToList(sail.dataset.value);
            validateSails();
        };
    };
    var s = table.querySelectorAll(".finish_input");
    for (var i = 0; i < s.length; i++) {
        s[i].style.cursor = "pointer";
        s[i].onclick = cf(s[i]);
        FINISH_INPUT.push(s[i]);
        POSSIBLE_VALUES.push(s[i].dataset.value);
    }
    s = table.querySelectorAll(".finish_check");
    for (i = 0; i < s.length; i++) {
        var el = document.createTextNode("");
        s[i].appendChild(el);
        FINISH_CHECK.push(el);
    }
    s = table.querySelectorAll(".finish_output");
    for (i = 0; i < s.length; i++) {
        s[i].addEventListener('change', validateSails, false);
        FINISH_OUTPUT.push(s[i]);
    }

    validateSails();
}, false);
})();
