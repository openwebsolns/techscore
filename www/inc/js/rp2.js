// Functions for validating rp form entry
// Dayan Paez
// Noevember 6, 2008
var allowed;
// Daddy function
function check() {
    var DEBUG = document.getElementById("jsdebug");

    // Get skippers and crews
    var elems = document.getElementsByTagName("select");
    var skcrs = new Array();
    var skcrv = new Array();
    for (var i = 0; i < elems.length; i++) {
	if (elems[i].name.substring(0,2) == "sk" ||
	    elems[i].name.substring(0,2) == "cr" ) {

	    // Get associated textbox
	    var t_elems = document.getElementsByName("r" + elems[i].name);
	    var t       = t_elems[0];

	    skcrs.push(elems[i]);
	    skcrv.push(t);
	}
    }
    
    // Check one sailor at a time
    for ( var s = 0; s < skcrs.length; s++ ) {
	// Get check box and data for this sailor
	var checkTD = getCheckTD(skcrs[s]);
	checkTD.innerHTML = "";
	typ_s = getType(skcrs[s]);
	div_s = getDiv (skcrs[s]);
	pos_s = getPos (skcrs[s]);

	// Get allowed races for this regatta
	var raceTD = document.getElementsByName("races" + div_s);
	var races = raceTD[0].innerHTML;
	for (var r = 1; r < raceTD.length; r++) {
	    races += "," + raceTD[r].innerHTML;
	}
	allowed = parseRange(races);
	val_s = parseRange(skcrv[s].value.replace(" ", ""));
	// Keep only that which is in the range
	var com = arrayCommon(val_s,allowed);
	skcrv[s].value = makeRange(com);

	// Create lists relating race number to number of crews
	var occTD  = document.getElementsByName("occ"   + div_s);
	occs = Array(races.length);
	for (var oc = 0; oc < occTD.length; oc++) {
	    var some_races = parseRange(raceTD[oc].innerHTML);
	    for (var r = 0; r < some_races.length; r++) {
		occs[some_races[r] - 1] = occTD[oc].innerHTML;
	    }
	}

	var errors = false;
	var warnings = false;

	// First check the validity of the text against the allowed
	// Get list of requested values
	if ( skcrs[s].value == "" || val_s.length == 0 ) {
	    checkTD.innerHTML = '<img alt="?" title="Waiting for input" src="img/question.png"/>';
	}
	else {
	    // **** 0 ****  Is there room on the boat for the crew?
	    if (typ_s == "cr") {

		var conflicting_race = Array();
		// For each race
		for (var r = 0; r < com.length; r++) {
		    var num_crews = 1; // counting this one
		    // Check against all previous crews in same division
		    for (var c = 0; c < Number(pos_s); c++) {
			var o_crew_n  = "cr"  + div_s + String(c);
			var o_crew_id = "rcr" + div_s + String(c);
			var o_crew = document.getElementsByName(o_crew_id);
			var o_crew_n = document.getElementsByName(o_crew_n);
			o_crew = o_crew[0];
			o_crew_n = o_crew_n[0];
			o_val  = o_crew.value;
			o_val_opt = o_crew_n.options[o_crew_n.selectedIndex];
			o_val_opt = o_val_opt.value;
			if (o_val.length > 0 && o_val_opt.length > 0) {
			    var duplicate = arrayCommon(parseRange(o_val),
							[com[r]]);
			    if (duplicate.length > 0)
				num_crews += 1;
			}
		    }

		    // Check for too many crews
		    if (num_crews > occs[com[r] - 1])
			conflicting_race.push(com[r]);
		}
		if (conflicting_race.length > 0) {
		    var conflict = makeRange(conflicting_race);
		    checkTD.innerHTML = '<img alt="X" title="Too many crews for ' + conflict + '" src="img/error.png"/> ' + conflict;
		    break;
		}
	    }

	    // Compare these values against all others in the form
	    for ( var s2 = 0; s2 < skcrs.length; s2++ ) {

		typ_s2 = getType(skcrs[s2]);
		div_s2 = getDiv (skcrs[s2]);
		pos_s2 = getPos (skcrs[s2]);
		val_s2 = parseRange(skcrv[s2].value.replace(" ", ""));

		if ( s != s2 &&
		     skcrs[s2].value != "" &&
		     val_s2.length > 0 ) {

		    // Races in common
		    var com = arrayCommon(val_s, val_s2);

		    // **** 1 **** Check for duplicate races within same
		    // division and role, but only for skippers
		    if ( typ_s2 == typ_s &&
			 typ_s2 == "sk"  &&
			 div_s2 == div_s   &&
			 com.length > 0 ) {
			// Repeats, report errors
			getCheckTD(skcrs[s]).innerHTML = '<img alt="Error" title="Multiple sailors for same race" src="img/error.png"/><strong>' + makeRange(com) + '</strong>';
			// Stop checking any more for this sailor
			errors = true;
			break;
		    }

		    // **** 2 **** Check for multipresence in different divisions and positions
		    if ( skcrs[s].value == skcrs[s2].value &&
			 (typ_s2 != typ_s || div_s2 != div_s) &&
			 com.length > 0 ) {
			// Alert problem
			checkTD.innerHTML = '<img alt="Error" title="Only God is omnipresent" src="img/error.png"/>';
			checkTD.innerHTML+= '<span><strong> ' + makeRange(com) + ' in ' + div_s2 + '</strong></span>';
			errors = true;
			break;
		    }

		    // **** 3 **** RULE 1: Skipper in one division cannot sail in any other division
		    if ( typ_s == "sk" &&
			 div_s != div_s2 &&
			 skcrs[s].value == skcrs[s2].value ) {
			// Warn of problem
			checkTD.innerHTML += '<img alt="Warning" title="Skippers cannot switch division" src="img/warn.png"/>';
			warnings = true;
		    }

		    // **** 4 **** RULE 2: Crews can only switch division once
		    if ( typ_s == "cr" &&
			 div_s != div_s2 &&
			 skcrs[s].value == skcrs[s2].value ) {
			// Let's make sure they're not switching back and forth
			val_all = val_s.concat(val_s2);
			val_all.sort(function(a,b){return a-b});

			// DEBUG.innerHTML += "<h5>ready to begin switch checking" + val_all + "</h5>";
			
			// For each race in val_all, check in which array it is located
			// If it switches arrays more than once, then rule is broken
			var switches = 0;
			var last;
			var curr;
			while ( switches <= 2 && val_all.length > 0 ) {
			    var r = val_all.shift();
			    // DEBUG.innerHTML += "<h5>r: " + r + "</h5>";
			    // DEBUG.innerHTML += "<p>com: " + arrayCommon(new Array(r), val_s) + "</p>";
			    var r_array = new Array();
			    r_array[0]  = r;
			    if ( arrayCommon(r_array, val_s).length > 0 ) {
				// It is in s array
				curr = val_s;
			    }
			    else {
				curr = val_s2;
			    }
			    // Compare where it is now, to where it once was
			    if ( curr != last ) {
				switches++;
				last = curr;
			    }
			}
			if ( switches > 2 ) {
			    // Too many switches, warn
			    checkTD.innerHTML += '<img alt="Warning" title="Crews can switch divisions only once" src="img/warn.png"/>';
			    warnings = true;
			}
		    }
		} // endif for checking oneself
	    }// end loop

	    // If, after all this, there are no errors, or warnings, type check!
	    if ( !errors && !warnings ) {
		checkTD.innerHTML = '<img alt="Check!" src="img/check.png"/>';
	    }
	    if ( !errors ) {
		document.getElementById('rpsubmit').disabled = false;
	    }
	    else {
		document.getElementById('rpsubmit').disabled = true;
	    }
	}
    }
}


// Gets common entries in two arrays
function arrayCommon(arr1, arr2) {
    var arr3 = new Array();
    var n    = 0;
    for (var i = 0; i < arr1.length; i++) {
	for (var j = 0; j < arr2.length; j++) {
	    if (arr1[i] == arr2[j]) {
		arr3[n] = arr1[i];
		n++;
		break;
	    }
	}
    }
    return arr3;
}

function sort_unique(list) {
    // Create a copy of list with pointers
    var l2 = new Array();
    for (var c in list) {
	l2[list[c]] = c;
    }
    // Translate back
    var l3 = new Array();
    var i = 0;
    for (var c in l2) {
	l3[i] = c;
	i++;
    }

    l3.sort(function(a,b){return a - b});
    return l3;
}

// Returns "sk" or "cr" from passed element's name
function getType(element) {
    return element.name.substring(0,2);
}
// Returns division from passed element's name
function getDiv(element) {
    return element.name.substring(2,3);
}
// Returns position from passed element's name
function getPos(element) {
    return element.name.substring(3);
}
// Returns element for validating input for given element
function getCheckTD(element) {
    return document.getElementById("c" + element.name);
}

// Parses entries for new students
function parseNames() {
    var textarea = (document.getElementById("name-text")).value;
    var table    = document.getElementById("name-valid");
    // Empty table
    while (table.rows.length > 0) {
	table.deleteRow(0);
    }

    lines = textarea.split("\n");
    for (i = 0; i < lines.length; i++) {
	if (lines[i] != "") // empty line
	    parseLine(lines[i],table)
    }
}

// Helper function
function parseLine(l, tab) {
    // Make l neat
    l = l.replace(/^\s+/,'');
    l = l.replace(/\s+/g,' ');
    l = l.replace(/\s+$/,'');
    var tokens = l.split(" ",3);

    // Add row
    var row = tab.insertRow(tab.rows.length);
    // Add entries to row
    for (var i = 0; i < tokens.length; i++) {
	var cell = row.insertCell(i);
	cell.innerHTML = tokens[i];
    }
}