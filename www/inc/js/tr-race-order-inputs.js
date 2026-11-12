/*
 * Deal with "number of boats" input in response to "rotation
 * frequency" as part of team race round creation.
 */

(function(w, d) {
    var frequency_input = d.getElementById('input_rotation_frequency');
    var num_boats_input = d.getElementById('input_num_boats');

    if (!frequency_input || !num_boats_input) {
        // nothing to do
        return;
    }

    var updateNumBoats = function(e) {
        if (frequency_input.value == "none") {
            num_boats_input.parentNode.classList.add("hidden");
            num_boats_input.required = false;
        } else {
            num_boats_input.parentNode.classList.remove("hidden");
            num_boats_input.required = true;
        }
    };

    frequency_input.addEventListener('change', updateNumBoats, false);
    updateNumBoats(new Event('load'));
})(window, document);
