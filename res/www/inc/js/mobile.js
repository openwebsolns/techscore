/**
 * JS for mobile devices: hide and show the menu, for one
 *
 */

// Toggle checkboxes with the provided className
// according to the checkbox with the same ID
function toggleMenu () {
    // Get menu class
    var m = document.getElementById("menudiv");
    if (m.className.indexOf("hidden") >= 0) {
	m.className = "";
    }
    else {
	m.className = "hidden";
    }
}

// Javascript for form handling
// Dayan Paez
// August 17, 2008

// Creates a range from a given array
// E.g.:  1  2  3  4  6  7 10
// outputs 1-4,6-7,10
function makeRange(list) {
    // Must be unique
    if (list.length == 0)
	return "";
    
    list = sort_unique(list);

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
}


// Parse range: takes in a string of numbers separated by comma's
// and dashes (-) to indicate a range and creates an array of numbers
// from the given string.
// Example: 1-4,5,6-8 means 1,2,3,4,5,6,7,8
function parseRange(str) {

    if ( str.toUpperCase() == "ALL" )
	return allowed;

    if ( str.length == 0 )
	return new Array();

    var list = new Array();
    var n = 0; // Index for list
    // Separate value at commas
    var sub   = str.split(",");
    for (var i = 0; i < sub.length; i++) {
	var delims = sub[i].split("-");

	for (var j = (Number)(delims[0]); j <= (Number)(delims[delims.length-1]); j++) {
	    list[n] = j;
	    n++;
	}
    }

    return list;
}

// Global jQuery and user interface scripts
//
// February 3, 2009
$(document).ready(function(){
	  // Automatically hide class "accessible"
	  $(".accessible").css("display","none");

  	// Calendar
    var inp = document.getElementById("datepicker");
    if (inp !== null && inp.type == "text") {
        var s = document.createElement("script");
        s.type = "text/javascript";
        s.src = "/inc/js/ui.datepicker.js";
        s.onload = function(evt) {
	          $('#datepicker').datepicker({firstDay: 1,
		                                     gotoCurrent: true,
		                                     currentText: 'Current'
		                                    });
        };
        var p = document.getElementsByTagName("script")[0];
        p.parentNode.insertBefore(s, p);

    }

	  toggleMenu();
});

