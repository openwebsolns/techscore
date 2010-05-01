// Javascript for sail page
// Dayan Paez
// September 18, 2008
//
// January 22, 2009
//   - If javascript is enabled, don't submit the page on choice of
// rotation or races. Rather, change dynamically the
// value of the hidden input elements in the form.

// Validate min_races
function validateMinRaces(id) {
    // Get boundary values
    var minimum = Number(document.getElementById('min_races').innerHTML);
    var maximum = Number(document.getElementById('max_races').innerHTML);
    var item    = document.getElementById(id);

    if  ( Number(item.value) < minimum || 
	  Number(item.value) != item.value || 
	  Number(item.value) > maximum) {
    	item.value = minimum;
    }
}

// Similar to above, but with max
function validateMaxRaces(id) {
    // Get boundary values
    var minimum = Number(document.getElementById('min_races').innerHTML);
    var maximum = Number(document.getElementById('max_races').innerHTML);
    var item    = document.getElementById(id);

    if  ( Number(item.value) > maximum || 
	  Number(item.value) != item.value || 
	  Number(item.value) < Number(document.getElementById("frace").value)) {

	item.value = maximum;
    }
}

function validateNumRaces(id) {
    // Get boundary values
    var minimum = Number(document.getElementById('frace').value);
    var maximum = Number(document.getElementById('trace').value);
    var item = document.getElementById(id);

    if ( Number(item.value) != item.value ||
	 Number(item.value) <= 0     ||
	 Number(item.value) > (maximum-minimum) )
	item.value = 2; // Default
}

// Change input elements
function sail_update(from, to) {
    f = document.getElementById(from);
    t = document.getElementById(to);
    // If it's an offset rotation request, submit the form.
    if (f.value == "OFF") {
	document.getElementById('sail_setup').submit();
	return;
    }

    t.value = f.value;
}
