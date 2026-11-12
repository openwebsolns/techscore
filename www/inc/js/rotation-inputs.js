/*
 * Toggle rotation inputs based on other parameters.
 */

(function(w, d) {
    var input_rotation_type = d.getElementById('input_rotation_type');
    var input_rotation_style = d.getElementById('input_rotation_style');
    var input_division_order = d.getElementById('input_division_order');
    var input_races_per_set = d.getElementById('input_races_per_set');

    if (!input_rotation_type) {
        // nothing to do
        return;
    }

    var hideInput = function(input) {
        if (input) {
            input.parentNode.classList.add("hidden");
            input.required = false;
        }
    };
    var showInput = function(input) {
        if (input) {
            input.parentNode.classList.remove("hidden");
            input.required = true;
        }
    };
    var updateForRotationType = function(e) {
        if (input_rotation_type.value == "none") {
            hideInput(input_races_per_set);
        } else {
            showInput(input_races_per_set);
        }
    };
    var updateForRotationStyle = function(e) {
        if (input_rotation_style.value == "copy") {
            hideInput(input_division_order);
        } else {
            showInput(input_division_order);
        }
    };

    input_rotation_type.addEventListener('change', updateForRotationType, false);
    updateForRotationType(new Event('load'));
    if (input_rotation_style) {
        input_rotation_style.addEventListener('change', updateForRotationStyle, false);
        updateForRotationStyle(new Event('load'));
    }
})(window, document);
