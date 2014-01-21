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

(function(w,d,g) {
    var f = function(e) {
        var acc = d.querySelectorAll(".accessible");
        for (var i = 0; i < acc.length; i++)
            acc[i].style.display = "none";

        // Announcements
        var ul = d.getElementById("announcements");
        if (ul) {
            var cf = function(li) {
                return function(e) {
                    li.parentNode.removeChild(li);
                };
            };
            for (i = 0; i < ul.childNodes.length; i++) {
                var li = ul.childNodes[i];
                li.style.position = "relative";
                var a = document.createElement("img");
                a.src = "/inc/img/c.png";
                a.setAttribute("alt", "X");
                a.style.position = "absolute";
                a.style.top = "30%";
                a.style.right = "0";
                a.style.cursor = "pointer";
                a.onclick = cf(li);
                li.appendChild(a);
            }
        }
        toggleMenu();
    };
    if (w.addEventListener)
        w.addEventListener('load', f, false);
    else {
        var old = w.onload;
        w.onload = function(e) {
            f(e);
            if (old)
                old(e);
        };
    }
})(window,document,"script");
