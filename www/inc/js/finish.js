// Functions specific to entering scores
// Dayan Paez
// September 10, 2008

// Initial setup
// document.getElementById("pos_sails").className = "hidden";

// Reverse a string
// from http://www.bytemycode.com/snippets/snippet/400/
String.prototype.reverse = function(){
    splitext = this.split("");
    revertext = splitext.reverse();
    reversed = revertext.join("");
    return reversed;
}

// Check that all the teams are different
    function checkTeams() {
	// Get finish button
	var submit_button = document.getElementById("submitfinish");
	// Get possible values
	var pos_teams = document.getElementsByName("pos_team");

	// Reset pos_values
	for (var i = 0; i < pos_teams.length; i++) {
	    pos_teams[i].style.visibility = "visible";
	}

	// Determining that submission is valid
	var can_submit = true;

	// Get all inputs
	var list   = document.getElementById("finish_list");
	var inputs = list.getElementsByTagName("select");

	// Go through each input and check it against the others later
	// in the list
	for (var i = 0; i < inputs.length; i++) {
	    var input = inputs[i];
	    var iNum  = input.id.replace("team", "");
	    var cell  = document.getElementById("check" + iNum);

	    // Check that it is not blank
	    if (input.value != "") {
		cell.src = "/img/check.png";
		cell.alt = "Valid";

		// Remove from the list of pos_teams
		for (var j = 0; j < pos_teams.length; j++) {
		    if (pos_teams[j].attributes.value.nodeValue == input.value) {
			pos_teams[j].style.visibility = "hidden";
			break;
		    }
		}

		// Compare to others preceding
		for (var k = 0; k < i; k++) {
		    if (inputs[k].value == input.value) {
			// Error! Alert both, and stop checking for
			// errors
			cell.src = "/img/error.png";
			cell.alt = "Error: repeated team";

			var iNum2 = inputs[k].id.replace("team", "");
			var cell2 = document.getElementById("check" + iNum2);
			cell2.src = "/img/error.png";
			cell2.alt = "Error: repeated sail";
			can_submit = false;
			break;
		    }
		}
	    } // end non-blank if
	    else {
		cell.src = "/img/question.png";
		cell.alt = "Waiting for input";
		can_submit = false;
	    }
	} // end: for each input

	submit_button.disabled = (!can_submit);
    }

// Check that all the sails are correct
// And indicate those that are repeated
// or illegal
function checkSails () {
    // Get finish button
    var submit_button = document.getElementById("submitfinish");
    // Get possible values
    var pos_sails = document.getElementsByName("pos_sail");
    // Reset pos_values
    for (var i = 0; i < pos_sails.length; i++) {
	pos_sails[i].style.visibility = "visible";
    }

    // Variable for determining that everything is okay
    // for submission
    var can_submit = true;

    // Get all inputs
    var list   = document.getElementById("finish_list");
    var inputs = list.getElementsByTagName("input");

    // Go through each input, and check it against the others
    // Handshake problem, extraordinaire
    for ( var i = 0; i < inputs.length; i++ ) {
       	var input = inputs[i];
	var iNum  = input.id.replace("sail","");
	var cell = document.getElementById("check" + iNum);

	// Check that it is a possible sail to begin with
	// If indeed, it's not blank, and a decision hasn't been made
	if ( input.value != "") {
	    var possible = false;
	    for (var j = 0; j < pos_sails.length; j++) {
		if (pos_sails[j].innerHTML == input.value) {
		    possible = true;
		    // Remove such a sail from view
		    pos_sails[j].style.visibility = "hidden";
		    break;
		}
	    }

	    // If possible, compare to others.
	    // If not, label as error
	    if ( !possible ) {
		cell.src = "/img/error.png";
		cell.alt = "Error in sail number";
		can_submit = false;
	    }
	    else {
		cell.src = "/img/check.png";
		cell.alt = "Valid";

		for (var k = 0; k < inputs.length; k++) {
		    if ( k != i && inputs[k].value == input.value ) {
			// Error! Alert both boxes, and stop
			// checking for errors
			cell.src = "/img/error.png";
			cell.alt = "Error: repeated sail";

			var iNum2 = inputs[k].id.replace("sail","");
			cell.src = "/img/error.png";
			cell.alt = "Error: repeated sail";
			can_submit = false;
			break;
		    }
		}
	    }
	} // End non-blank if
	else {
	    cell.src = "/img/question.png";
	    cell.alt = "Waiting for input";
	    can_submit = false;
	}
    } // for each input
    // Determine submit button's fate
    if ( can_submit ) {
	submit_button.disabled = false;
    }
    else {
	submit_button.disabled = true;
    }
}

/**
 * Check the race argument is valid, and submit form, if so
 */
var LAST_RACE_VALUE;
function setRaceValue(val) {
    LAST_RACE_VALUE = val;
}

function checkRace() {
    var form = document.getElementById('race_form');
    var race_elem = document.getElementById('chosen_race');
    var race = race_elem.value;

    race = race.replace(/ /g, "");
    race = race.replace(/ /g, "-");
    race = race.toUpperCase();
    race_rev = race.reverse();

    if (race.indexOf("A") < 0 &&
	race.indexOf("B") < 0 &&
	race.indexOf("C") < 0 &&
	race.indexOf("D") < 0) {

	alert('Invalid or missing division');
	race_elem.value = LAST_RACE_VALUE;
	race_elem.focus();
	return;
    }
    
    // Extract division and number
    if (race.substring(0,1) == "A" ||
	race.substring(0,1) == "B" ||
	race.substring(0,1) == "C" ||
	race.substring(0,1) == "D") {

	var division = race.substring(0,1);
	var number   = race.substring(1);
    }
    else if (race_rev.substring(0,1) == "A" ||
	     race_rev.substring(0,1) == "B" ||
	     race_rev.substring(0,1) == "C" ||
	     race_rev.substring(0,1) == "D") {

	var division = race_rev.substring(0,1);
	var number   = race_rev.substring(1);
    }
    else {
	alert('Invalid race: ' + race);
	race_elem.value = LAST_RACE_VALUE;
	return;
    }

    // Validate division and race number
    var divrow  = document.getElementById('pos_divs');
    var divcells = divrow.cells;

    var racerow = document.getElementById('pos_races');
    var racecells = racerow.cells;

    var valid = false;
    for (var i = 0; i < divcells.length; i++) {
	if (divcells[i].innerHTML == division &&
	    Number(racecells[i].innerHTML) >= Number(number) &&
	    0 < Number(number)) {
	    valid = true;
	    break;
	}
    }

    // Complain if invalid
    if (!valid) {
	alert('Invalid race number');
	race_elem.value = LAST_RACE_VALUE;
	return;
    }

    form.submit();
}

// Appends the passed value to next available spot in list
function appendToList(elem) {
    // Get all inputs
    var list   = document.getElementById("finish_list");
    var inputs = list.getElementsByTagName("input");
    var selects = list.getElementsByTagName("select");

    for (var i = 0; i < inputs.length; i++) {
	var input = inputs[i];
	if (input.value == "") {
	    input.value = elem.innerHTML;
	    checkSails();
	    break;
	}
    }
    for (var i = 0; i < selects.length; i++) {
	var select = selects[i];
	if (select.value == "") {
	    select.value = elem.attributes.value.nodeValue;
	    checkTeams();
	    break;
	}
    }
}

// Update compass
function updateCompass() {
    // Get the wind_dir selected
    var sel = document.getElementById('wind_dir');
    var dir = (sel.options[sel.selectedIndex]).value;

    drawCompass(dir.toLowerCase());
}

// Draw iteration
function updateDirectionSelect(dir) {
    // Get the wind_dir select
    var sel = document.getElementById('wind_dir');
    var opt = sel.options;
    for (var i = 0; i < opt.length; i++) {
	if (opt[i].value == dir) {
	    sel.selectedIndex = i;
	    break;
	}
    }
}

// Draw compass
function drawCompass(dir) {
    var pic = document.getElementById('compass_pic');
    pic.src = "/img/needle_" + dir + ".png";
}

/**
 * Compass javascript to choose one of 16 "cardinal" directions, using
 * the mouse.
 *
 * @author Dayan Paez
 * @date   January 26, 2009
 */
var DIRECTION = null;
var PIC_ROOT  = "/img/needle_";

// Get coordinates of click
$(document).ready(function() {

	$("#compass").click(function(ev) {
		var x = ev.pageX - this.offsetLeft;
		var y = ev.pageY - this.offsetTop;
		
		// center location
		var cX = cY = 50;
		x -= cX;
		y =  cY - y;
		var resolution = 15; // degree resolution
		
		angle = Math.atan2(y,x)*180.0/3.14159;
		var dir = null;
		if ( Math.abs(angle) < resolution ) { // east
		    dir = "e";
		}
		if ( Math.abs(angle-22.5) < resolution ) { // ene
		    dir = "ene";
		}
		if ( Math.abs(angle-45) < resolution ) { // ne
		    dir = "ne";
		}
		if ( Math.abs(angle-67.5) < resolution ) { // nne
		    dir = "nne";
		}
		if ( Math.abs(angle-90) < resolution ) { // n
		    dir = "n";
		}
		if ( Math.abs(angle-112.5) < resolution ) { // nnw
		    dir = "nnw";
		}
		if ( Math.abs(angle-135) < resolution ) { // nw
		    dir = "nw";
		}
		if ( Math.abs(angle-157.5) < resolution ) { // wnw
		    dir = "wnw";
		}
		if ( Math.abs(angle-180) < resolution ) { // w
		    dir = "w";
		}
		if ( Math.abs(angle+180) < resolution ) { // w
		    dir = "w";
		}
		if ( Math.abs(angle+157.5) < resolution ) { // wsw
		    dir = "wsw";
		}
		if ( Math.abs(angle+135) < resolution ) { // sw
		    dir = "sw";
		}
		if ( Math.abs(angle+112.5) < resolution ) { // ssw
		    dir = "ssw";
		}
		if ( Math.abs(angle+90) < resolution ) { // s
		    dir = "s";
		}
		if ( Math.abs(angle+67.5) < resolution ) { // sse
		    dir = "sse";
		}
		if ( Math.abs(angle+45) < resolution ) { // se
		    dir = "se";
		}
		if ( Math.abs(angle+22.5) < resolution ) { // ese
		    dir = "ese";
		}
		
		drawCompass(dir);
		updateDirectionSelect(dir.toUpperCase());
	    });


	// Disable the finish button
	$("#submitfinish").attr({disabled:true});

	// Make possible sails grabbable
	$(".pos_sail").css("cursor", "pointer");
	$(".pos_sail").click(function() {
		appendToList(this);
	    });

	checkSails();
	checkTeams();
    });