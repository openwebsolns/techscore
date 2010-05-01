// Javascript for form handling
// Dayan Paez
// August 17, 2008

// Toggle checkboxes with the provided className
// according to the checkbox with the same ID
function toggleMenu () {
    // Get menu class
    var m = document.getElementById("menudiv");
    var b = document.getElementById("bodydiv");
    var h = document.getElementById("headdiv");
    var l = document.getElementById("menulink");
    var t = document.getElementById("menulinkbutton");
    // Get all divs
    var ports = document.getElementsByTagName("div");
    if (m.className.indexOf("hidden") >= 0) {
	m.className = "";
	b.className = b.className.replace("fullwidth","");
	h.className = h.className.replace("fullwidth","");
	l.className = l.className.replace("tiny","");
	t.innerHTML = "Hide";
    }
    else {
	m.className = "hidden";
	b.className += " fullwidth";
	h.className += " fullwidth";
	l.className += " tiny";
	t.innerHTML = ">";
    }
}


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

/**
 * Opens the dialog box
 *
 */
function open_dialog(loc) {
    window.open(loc,
		loc,
		'height=600px,width=400px,location=no,menubar=no,status=no,toolbar=no,scrollbars=yes');
}

// Global jQuery and user interface scripts
//
// February 3, 2009
$(document).ready(function(){
	// Automatically hide class "accessible"
	$(".accessible").css("display","none");

	$("#right-grab").addClass("scriptenabled");
	$("#right-grab").click( function(){
		if($("#menudiv").is(":hidden")) {
		    $("#menudiv").show();
		    $("#headdiv").removeClass("fullscreen");
		    $("#bottom-grab").removeClass("fullscreen");
		    $("#bodydiv").removeClass("fullscreen");
		    $("#right-grab").removeClass("fullscreen");
		}
		else {
		    $("#menudiv").hide();
		    $("#headdiv").addClass("fullscreen");
		    $("#bottom-grab").addClass("fullscreen");
		    $("#bodydiv").addClass("fullscreen");
		    $("#right-grab").addClass("fullscreen");
		}
	    });

	// Tablehover
	$('.coordinate').tableHover({colClass: 'hover',
		    cellClass:'hovercell'});
	$('.ordinate').tableHover();
	//clickClass:'click'});

	// Hide/show columns
	$('#hide').click(function(){
		var l = parseRange($('#hidetext').val());
		for (var i = 0; i < l.length; i++) {
		    l[i] += 3;
		}
		$('#results').hideColumns(l); 
	    });

	$('#show').click(function(){
		$('#results').showColumns(null); });



	// Calendar
	$('#datepicker').datepicker({firstDay: 1,
		    gotoCurrent: true,
		    currentText: 'Current'
		    });

	// Dialogs
	$(".dialog").removeAttr("href");
        $(".dialog").click(function() {
		open_dialog($(this).attr("title"));
	    });

    });
