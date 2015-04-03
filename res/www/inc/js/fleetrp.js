/*
 * Handle fleet racing RP form validation.
 *
 * Define a top-level object, since it may need to be triggered from other parts
 * of the code.
 *
 * @author Dayan Paez
 * @version 2015-04-01
 */

/**
 * Create a new FleetRp handler.
 *
 * @param formElement the parent element to search for.
 * @param settings a simple map with potential parameters:
 *
 *    - enforceDivisionSwitch (default = true)
 */
function FleetRp(formElement, settings) {

    if (!(formElement instanceof HTMLElement)) {
        throw "Invalid formElement provided: must be HTMLElement.";
    }
    this.rootElement = formElement;

    // Process settings
    this.settings = {
        enforceDivisionSwitch: true
    };
    if (settings) {
        for (var key in this.settings) {
            if (key in settings) {
                this.settings[key] = settings[key];
            }
        }
    }

    // faux constants for validation
    this.WAITING = "waiting";
    this.ERROR = "error";
    this.VALID = "valid";

    // listener to attach to every input/select element we process
    var myObj = this;
    this.checkListener = {
        handleEvent: function(e) {
            myObj.check();
        }
    };

    this.loadCrewsPerDivision();
    this.loadRacesPerDivision();
    // skippers and crews are provided as <select> elements under the given root 
    // element. We keep a reference to only this "live" collection, which is
    // automatically updated with the DOM.
    this.sailors = this.rootElement.getElementsByTagName("select");
    this.check();
}

/**
 * Adds a listener to given input's "change" element to call check method.
 *
 * From MDN: If multiple identical EventListeners are registered on the same
 * EventTarget with the same parameters, the duplicate instances are
 * discarded. They do not cause the EventListener to be called twice, and since
 * the duplicates are discarded, they do not need to be removed manually with
 * the removeEventListener method.
 *
 */
FleetRp.prototype.registerInput = function(elem) {
    elem.addEventListener("change", this.checkListener, false);
};

/**
 * JSON-decodes root element's crewsPerDivision dataset entry, which specifies
 * the number of crews allowed in each race per division.
 *
 */
FleetRp.prototype.loadCrewsPerDivision = function() {
    this.crewsPerDivision = JSON.parse(this.rootElement.dataset.crewsPerDivision);
};

/**
 * Updates the list of racesPerDivision, based on crewsPerDivision. Must be called
 * after that method.
 */
FleetRp.prototype.loadRacesPerDivision = function() {
    this.racesPerDivision = {};
    for (var div in this.crewsPerDivision) {
        this.racesPerDivision[div] = this.sortUnique(
            Object.keys(this.crewsPerDivision[div])
        );
    }
};

/**
 * Parses internal list of sailors, and returns structured list for checking.
 *
 * The returned value is a list of objects with the following attributes:
 *
 *   - division
 *   - role:      either "crew" or "skipper"
 *   - sailor:    the chosen value.
 *   - races:     the value
 *   - racesBox:  the corresponding <input> element.
 *   - sailorBox: the <select> element.
 *   - checkBox:  the box where validation information is provided.
 *
 * Only <select> elements in our collection that contain the following dataset
 * values are included in the returned list, and only if a corresponding races
 * and check box are found based on ID.
 *
 *   - rpDivision
 *   - rpRole
 */
FleetRp.prototype.prepare = function() {
    var output = [];
    var i, search, sailorBox, racesBox, checkBox;
    for (i = 0; i < this.sailors.length; i++) {
        sailorBox = this.sailors[i];
        if ("rpDivision" in sailorBox.dataset
            && "rpRole" in sailorBox.dataset
            && ["skipper", "crew"].indexOf(sailorBox.dataset.rpRole) >= 0) {

            var div = sailorBox.dataset.rpDivision;
            var role = sailorBox.dataset.rpRole;
            var id = sailorBox.id;

            // find the corresponding textbox
            racesBox = document.getElementById(id.replace("sailor", "races"));
            checkBox = document.getElementById(id.replace("sailor", "check"));

            // only proceed if there's a match
            if (racesBox && checkBox) {
                var fixedRaceList = this.parseRaces(racesBox.value, div);
                racesBox.value = this.makeRange(fixedRaceList);
                output.push(
                    {
                        division:  div,
                        role:      role,
                        sailor:    sailorBox.value,
                        races:     fixedRaceList,
                        sailorBox: sailorBox,
                        racesBox:  racesBox,
                        checkBox:  checkBox
                    }
                );

                // by re-registering each time, we ascertain that updates to
                // the DOM automatically get registered.
                this.registerInput(sailorBox);
                this.registerInput(racesBox);
            }
        }
    }
    return output;
};


/**
 * Workhorse function: ascertains validity of form.
 *
 * Will check all sailors, one at a time, in the internal rp representation to
 * make sure that all necessary information is present and consistent.
 */
FleetRp.prototype.check = function() {
    var globalErrors = [];

    // track validated crews per division, in order to check if others fit
    var crews = {};
    var rpEntries = this.prepare();
    for (var i = 0; i < rpEntries.length; i++) {
        var rpEntry = rpEntries[i];

        // anything?
        if (!rpEntry.sailor && rpEntry.races.length == 0) {
            this.setCheckStatus(rpEntry.checkBox, this.WAITING);
            continue;
        }

        var errors = [];
        var warnings = [];

        // missing races?
        if (rpEntry.sailor && rpEntry.races.length == 0) {
            this.setCheckStatus(rpEntry.checkBox, this.ERROR, "Missing races");
            globalErrors.push(
                [rpEntry.role, "for", rpEntry.division, "division is missing races."].join(" ")
            );
            continue;
        }

        // missing sailor?
        if (!rpEntry.sailor && rpEntry.races.length > 0) {
            this.setCheckStatus(rpEntry.checkBox, this.ERROR, "Missing sailor");
            globalErrors.push(
                [rpEntry.role, "for", rpEntry.division, "division is missing the sailor."].join(" ")
            );
            continue;
        }

        // is there room?
        if (rpEntry.role == "crew") {
            if (!(rpEntry.division in crews)) {
                crews[rpEntry.division] = [];
            }

            var badRaces = this.getRacesWithNoRoomFor(rpEntry, crews[rpEntry.division]);
            if (badRaces.length > 0) {
                var message = "Too many crews in " + this.makeRange(badRaces);
                this.setCheckStatus(rpEntry.checkBox, this.ERROR, message);
                globalErrors.push(
                    [message, "for", rpEntry.division, "division."].join(" ")
                );
                continue;
            }

            crews[rpEntry.division].push(rpEntry);
        }

        // TODO:
    }
    console.log(globalErrors);
};

/**
 * Sets the given status in the given status box.
 *
 * @param cell the cell to update.
 * @param status the class "constant" to update to.
 * @param reason the optional reason to attach.
 */
FleetRp.prototype.setCheckStatus = function(cell, status, reason) {
    // TODO:
};

/**
 * Determines the races for which the given rpEntry is NOT allowed.
 *
 * Some assumptions: otherCrews contains "valid" crews. No extra validation
 * is performed in this method.
 *
 * @param rpEntry a structure for a crew with division and races.
 * @param otherCrews list of crews in the same division.
 * @return list of races with problems.
 */
FleetRp.prototype.getRacesWithNoRoomFor = function(rpEntry, otherCrews) {
    var badRaces = [];

    // process one race at a time
    for (var i = 0; i < rpEntry.races.length; i++) {
        var race = rpEntry.races[i];

        var numCrews = 1; // counting this one
        for (var j = 0; j < otherCrews.length; j++) {
            var otherEntry = otherCrews[j];
            if (otherEntry.races.indexOf(race) >= 0) {
                numCrews++;
            }
        }

        if (numCrews > this.crewsPerDivision[rpEntry.division][race]) {
            badRaces.push(race);
        }
    }

    console.log("Bad races=" + badRaces);
    return badRaces;
};

/**
 * Helper method to both sort and keep only unique values in list.
 *
 * @param list the list to sort and uniquify.
 * @return new sorted list.
 */
FleetRp.prototype.sortUnique = function(list) {
    // Create a copy of list with pointers
    var l2 = new Array();
    for (var c in list) {
	      l2[list[c]] = c;
    }
    // Translate back
    var l3 = new Array();
    var i = 0;
    for (c in l2) {
	      l3[i] = c;
	      i++;
    }

    l3.sort(function(a,b){return a - b;});
    return l3;
};

/**
 * Helper method to return sorted list of entries from string input.
 *
 * Will change "*" to equivalent set of races for given division.
 *
 * @param value the string to parse.
 * @param division the division in context.
 * @return list of numbers.
 */
FleetRp.prototype.parseRaces = function(value, division) {
    value = value.replace(" ", "");
    if (value == "")
        return [];
    if (value == "*")
        return this.racesPerDivision[division];

    var races = this.parseRange(value);
    return this.intersectionOf(races, this.racesPerDivision[division]);
};

/**
 * Translate range of numbers into matching list.
 *
 * Example: 1-4,5,6-8 means 1,2,3,4,5,6,7,8.
 *
 * @param string the range of numbers.
 * @return parsed list
 */
FleetRp.prototype.parseRange = function(range) {
    if ( range.length == 0 )
	      return new Array();

    var list = new Array();
    var n = 0; // Index for list
    // Separate value at commas
    var sub   = range.split(",");
    for (var i = 0; i < sub.length; i++) {
	      var delims = sub[i].split("-");

	      for (var j = (Number)(delims[0]); j <= (Number)(delims[delims.length-1]); j++) {
	          list[n] = j;
	          n++;
	      }
    }
    return list;
};

/**
 * Convert list of numbers into human-readable range.
 *
 * @param list a list of numbers
 * @return string like 1-4,6-9
 */
FleetRp.prototype.makeRange = function(list) {
    // Must be unique
    if (list.length == 0)
	      return "";
    
    list = this.sortUnique(list);

    var mid_range = false;
    var last  = list[0];
    var range = last;
    for (var i = 1; i < list.length; i++) {
	      if ((Number)(list[i]) == (Number)(last) + 1) {
	          mid_range = true;
	      }
	      else {
	          mid_range = false;
	          if (last != range.substring(range.length-last.length))
		            range += "-" + last;

	          range += "," + list[i];
	      }
	      last = list[i];
    }
    if ( mid_range )
	      range += "-" + last;

    return range;
};

/**
 * Combines two sorted lists into one with only elements found in both.
 *
 * @param list1 a list sorted in ascending order.
 * @param list2 a list sorted in ascending order.
 * @return new list with elements found in both.
 */
FleetRp.prototype.intersectionOf = function(list1, list2) {
    // because of assumption of ordered input lists, implement this
    // as the merge step of a merge sort.

    var merge = [];
    var i1 = 0;
    var i2 = 0;
    var prevValue = null;
    var value1, value2;
    while (i1 < list1.length && i2 < list2.length) {
        value1 = list1[i1];
        value2 = list2[i2];
        if (value1 < value2) {
            i1++;
            continue;
        }
        if (value1 > value2) {
            i2++;
            continue;
        }
        // at this point, they're equal
        if (prevValue != value1) {
            prevValue = value1;
            merge.push(prevValue);
        }
        i1++;
        i2++;
    }
    return merge;
};

// Launch at load time
window.addEventListener('load', function(evt) {
    var settings = {
        // Accept defaults
    };
    var obj = new FleetRp(document.getElementById("rpform"), settings);
}, false);
