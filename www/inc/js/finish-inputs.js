/*
 * Counterpart to EnterFinishPane
 */
(function(w, d) {
    w.addEventListener(
        'load',
        function(e) {
            var widget = d.getElementById("finishes-widget");
            if (!widget) {
                return;
            }

            var validClassname = "finish_check";
            var errorClassname = "finish_error";
            var promotedClassname = "promoted";
            var possibleValues = {};
            var finishElements = [];
            var submitInput = null;

            var getFeedbackElement = function(finishElement) {
                var parentCell = finishElement.parentNode;
                return parentCell.previousSibling;
            };

            var promoteOption = function(e) {
                e.preventDefault();
                var target = e.target;
                if (target.classList.contains(promotedClassname)) {
                    return;
                }
                for (var i = 0; i < finishElements.length; i++) {
                    if (finishElements[i].value == "") {
                        finishElements[i].value = target.dataset.value;
                        break;
                    }
                }
                validateInputs(e);
            };
            var validateInputs = function(e) {
                var isComplete = true;
                for (value in possibleValues) {
                    possibleValues[value].classList.remove(promotedClassname);
                }
                for (var i = 0; i < finishElements.length; i++) {
                    var feedbackElement = getFeedbackElement(finishElements[i]);
                    feedbackElement.classList.remove(validClassname);
                    feedbackElement.classList.remove(errorClassname);
                    var value = finishElements[i].value;
                    if (value == "") {
                        isComplete = false;
                        continue;
                    }

                    // Invalid?
                    if (!(value in possibleValues)) {
                        feedbackElement.classList.add(errorClassname);
                        isComplete = false;
                        continue;
                    }
                    possibleValues[value].classList.add(promotedClassname);

                    // Duplicate?
                    var foundDuplicate = false;
                    for (var j = 0; j < i; j++) {
                        var other = finishElements[j].value;
                        if (other != "" && other == value) {
                            feedbackElement.classList.add(errorClassname);
                            feedbackElement.classList.add(errorClassname);
                            foundDuplicate = true;
                        }
                    }
                    if (foundDuplicate) {
                        isComplete = false;
                        continue;
                    }

                    // Must be valid!
                    feedbackElement.classList.add(validClassname);
                }
                submitInput.disabled = !isComplete;
            };

            submitInput = d.getElementById("submitfinish");

            var elements = widget.getElementsByClassName("finishes-widget-option");
            for (var i = 0; i < elements.length; i++) {
                possibleValues[elements[i].dataset.value] = elements[i];
                elements[i].classList.add("finish_input");
                elements[i].style.cursor = "pointer";
                elements[i].addEventListener('click', promoteOption, false);
            }

            elements = widget.getElementsByClassName("finishes-widget-place");
            for (i = 0; i < elements.length; i++) {
                finishElements.push(elements[i]);
                elements[i].addEventListener('change', validateInputs, false);
            }
            finishElements.sort(function(a, b) {
                return a.getAttribute("tabindex") - b.getAttribute("tabindex");
            });

            // Helpful message in spacer
            elements = widget.getElementsByClassName("finishes-widget-spacer");
            for (i = 0; i < elements.length; i++) {
                elements[i].appendChild(document.createTextNode("Hint: click entry from second list to populate place finishes."));
            }

            validateInputs(new Event('load'));
        },
        false
    );
})(window, document);
