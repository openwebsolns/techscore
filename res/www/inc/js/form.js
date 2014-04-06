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
        var ul, cf, i;
        var acc = d.querySelectorAll(".accessible");
        for (i = 0; i < acc.length; i++)
            acc[i].style.display = "none";

	// Collapsible
	acc = d.querySelectorAll(".collapsible");
	var rf = function(p) {
	    return function(e) {
		p.classList.toggle("collapsed");
		return false;
	    };
	};
	if (acc.length > 0) {
	    for (i = 0; i < acc.length; i++) {
		acc[i].classList.add("collapsed");
		acc[i].classList.add("js");
		acc[i].childNodes[0].onclick = rf(acc[i]);
	    }
	}

        // Context menu?
        ul = d.getElementById("context-menu");
        if (ul)
            d.body.setAttribute("contextmenu", "context-menu");

        // Menus
        ul = d.getElementById("menubar");
        var s1 = d.getElementById("main-style");
        if (ul && s1) {
            i = 0;
            while (i < s1.sheet.cssRules.length) {
                if (s1.sheet.cssRules[i].selectorText == "#menubar .menu:hover ul"
                    || s1.sheet.cssRules[i].selectorText == "#menubar .menu:hover")
                    s1.sheet.deleteRule(i);
                else
                    i++;
            }

            cf = function(h4) {
                return function(e) {
                    var open = !h4.parentNode.classList.contains("open");
                    for (var i = 0; i < h4.parentNode.parentNode.childNodes.length; i++) {
                        h4.parentNode.parentNode.childNodes[i].classList.remove("open");
                    }
                    if (open) {
                        h4.parentNode.classList.add("open");
                    }
                };
            };
            var mf = function(ul) {
                return function(e) {
                    ul.parentNode.classList.remove("open");
                };
            };
            for (i = 0; i < ul.childNodes.length; i++) {
                var h4 = ul.childNodes[i].childNodes[0];
                h4.onclick = cf(h4);
                var sl = ul.childNodes[i].childNodes[1];
                sl.onclick = mf(sl);
            }
        }

        // Announcements
        ul = d.getElementById("announcements");
        if (ul) {
            cf = function(li) {
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
