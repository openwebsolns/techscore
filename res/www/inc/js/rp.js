// Functions for validating rp form entry
// Dayan Paez
// November 6, 2008
// Updated 2009-03-20
var allowed;
var ENFORCE_DIV_SWITCH = true;

// Daddy function
function check() {

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

    // Get list of permissible races and divisions
    var pos_divs = Array("A", "B", "C", "D");
    var pos_races = Array();
    var pos_occs  = Array();
    for (var d = 0; d < pos_divs.length; d++) {
	      var raceTD = document.getElementsByName("races" + pos_divs[d]);
	      if (raceTD.length > 0) {
	          // Parse content
	          // pos_races is an associative array with keys set to the
	          // divisions and with values equal to lists of the race
	          // numbers allowed in that division 
	          var races = raceTD[0].innerHTML;
	          for (var r = 1; r < raceTD.length; r++) {
		            races += "," + raceTD[r].innerHTML;
	          }
	          pos_races[pos_divs[d]] = parseRange(races);
	          
	          // Create lists relating race number to number of crews,
	          // the pos_occs array is an associative array with keys
	          // equal to divisions and with values equal to zero-based
	          // lists indicating how many crews are allowed in race =
	          // (index+1)
	          var occTD  = document.getElementsByName("occ"   + pos_divs[d]);
	          occs = Array(pos_races[pos_divs[d]].length);
	          for (var oc = 0; oc < occTD.length; oc++) {
		            var some_races = parseRange(raceTD[oc].innerHTML);
		            for (var r = 0; r < some_races.length; r++) {
		                occs[some_races[r] - 1] = occTD[oc].innerHTML;
		            }
	          }
	          pos_occs[pos_divs[d]] = occs;
	      }
    }

    // Check one sailor at a time, checking against all preceeding
    // sailors in the form
    var global_errors = false;
    for ( var s = 0; s < skcrs.length; s++ ) {
	      // Get check box and data for this sailor
	      var checkTD = getCheckTD(skcrs[s]);
	      checkTD.innerHTML = "";
	      var typ_s = getType(skcrs[s]);
	      var div_s = getDiv (skcrs[s]);
	      var pos_s = getPos (skcrs[s]);

        // parse '*' as "all races"
        var val_s = skcrv[s].value.replace(" ", "");
        if (val_s == "*")
            val_s = pos_races[div_s];
        else {
	          // Keep only that which is in the range
	          val_s = arrayCommon(parseRange(val_s), pos_races[div_s]);
        }
	      skcrv[s].value = makeRange(val_s);

	      var errors = false;
	      var warnings = false;

	      // First check the validity of the text against the allowed
	      // Get list of requested values
        if ( skcrs[s].value == "" && val_s.length == 0 ) {
	          checkTD.innerHTML = '<img alt="?" title="Waiting for input" src="/inc/img/question.png"/>';
	      }
	      else {
            // Are there any races?
            if ( skcrs[s].value != "" && skcrv[s].value == "") {
                checkTD.innerHTML = '<img alt="⚠" title="Missing races" src="/inc/img/i.png"/>';
                errors = true;
            }
            // Is there any sailor?
            if ( skcrs[s].value == "" && skcrv[s].value != "") {
                checkTD.innerHTML = '<img alt="⚠" title="Missing sailor" src="/inc/img/i.png"/>';
                errors = true;
            }
	          // **** 0 ****  Is there room on the boat for the crew?
	          else if (typ_s == "cr") {

		            var conflicting_race = Array();
		            // For each race
		            for (var r = 0; r < val_s.length; r++) {
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
			                      var duplicate = arrayCommon(parseRange(o_val), [val_s[r]]);
			                      if (duplicate.length > 0)
				                        num_crews += 1;
			                  }
		                }

		                // Check for too many crews
		                if (num_crews > pos_occs[div_s][val_s[r] - 1])
			                  conflicting_race.push(val_s[r]);
		            }
		            if (conflicting_race.length > 0) {
		                var conflict = makeRange(conflicting_race);
		                checkTD.innerHTML = '<img alt="X" title="Too many crews for ' + conflict + '" src="/inc/img/e.png"/> ' + conflict;
		                break;
		            }
	          }

	          // Compare these values against all others in the form
	          for ( var s2 = 0; s2 < skcrs.length; s2++ ) {

		            var typ_s2 = getType(skcrs[s2]);
		            var div_s2 = getDiv (skcrs[s2]);
		            var pos_s2 = getPos (skcrs[s2]);
		            var val_s2 = parseRange(skcrv[s2].value.replace(" ", ""));

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
			                            getCheckTD(skcrs[s]).innerHTML = '<img alt="Error" title="Multiple sailors for same race" src="/inc/img/e.png"/><strong>' + makeRange(com) + '</strong>';
			                            // Stop checking any more for this sailor
			                            errors = true;
			                            break;
		                          }

		                     // 2009-09-28: Removed this check
		                     /*
		                      // **** 2 **** Check for multipresence in different divisions and positions
		                      if ( skcrs[s].value == skcrs[s2].value &&
			                    (typ_s2 != typ_s || div_s2 != div_s) &&
			                    com.length > 0 ) {
			                    // Alert problem
			                    checkTD.innerHTML = '<img alt="Error" title="Only God is omnipresent" src="/inc/img/e.png"/>';
			                    checkTD.innerHTML+= '<span><strong> ' + makeRange(com) + ' in ' + div_s2 + '</strong></span>';
			                    errors = true;
			                    break;
		                      }
		                      */

		                     // **** 3 **** RULE 1: Skipper in one division cannot sail in any other division
		                     if ( typ_s == "sk" &&
                              ENFORCE_DIV_SWITCH &&
			                        div_s != div_s2 &&
			                        skcrs[s].value == skcrs[s2].value ) {
			                            // Warn of problem
			                            checkTD.innerHTML += '<img alt="Warning" title="Skippers cannot switch division" src="/inc/img/i.png"/>';
			                            warnings = true;
		                          }

		                     // **** 4 **** RULE 2: Crews can only switch division once
		                     if ( typ_s == "cr" &&
                              ENFORCE_DIV_SWITCH &&
			                        div_s != div_s2 &&
			                        skcrs[s].value == skcrs[s2].value ) {
			                            // Let's make sure they're not switching back and forth
			                            var val_all = val_s.concat(val_s2);
			                            val_all.sort(function(a,b){return a-b});

			                            
			                            // For each race in val_all, check in which array it is located
			                            // If it switches arrays more than once, then rule is broken
                                  
			                            var switches = 0;
			                            var last;
			                            var curr;
			                            while ( switches <= 2 && val_all.length > 0 ) {
			                                var r = val_all.shift();
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
			                                checkTD.innerHTML += '<img alt="Warning" title="Crews can switch divisions only once" src="/inc/img/i.png"/>';
			                                warnings = true;
			                            }
		                          }
		                 } // endif for checking oneself
	          }// end loop

	          // If, after all this, there are no errors, or warnings, type check!
	          if ( !errors && !warnings ) {
		            checkTD.innerHTML = '<img alt="Check!" src="/inc/img/s.png"/>';
	          }
	          if ( errors )
                global_errors = true;
	      }
    }
    document.getElementById('rpsubmit').disabled = global_errors;
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

// Returns list of races in the element
function getRaceList(element) {
    var value = element.value;
    return parseRange(value);
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
