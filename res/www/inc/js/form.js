// Javascript for form handling
// Dayan Paez
// August 17, 2008

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

        // Mobile menu
        var m = d.getElementById("menudiv");
        var h = d.getElementById("logo");
        if (m && h) {
            m.classList.add("m-menu-hidden");
            h.classList.add("m-menu-hidden");
            h.onclick = function(e) {
                m.classList.toggle("m-menu-hidden");
                h.classList.toggle("m-menu-hidden");
            };
        }

        // Context menu?
        ul = d.getElementById("context-menu");
        if (ul) {
            d.body.setAttribute("contextmenu", "context-menu");
        }

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

        // Help form
        var hf = d.getElementById("help-form");
        var hs = d.getElementById("help-wrap");
        if (hs && hf && XMLHttpRequest && FormData) {
            var mH = d.createElement("p");
            mH.id = "help-alert";
            mH.style.display = "none";
            hf.insertBefore(mH, hf.childNodes[2]);
            hf.addEventListener('submit', function(e) {
                hs.style.display = "none";
                // Perform request via AJAX
                var req = new XMLHttpRequest();
                req.onreadystatechange = function(s) {
                    if (s.target.readyState == 4) {
                        mH.style.display = "block";
                        while (mH.childNodes.length > 0)
                            mH.removeChild(mH.childNodes[0]);

                        if (s.target.response.error == 0) {
                            // Add message as success
                            mH.className = "valid";
                            mH.appendChild(d.createTextNode(s.target.response.message));
                            for (i = 0; i < hf.length; i++) {
                                if (hf[i].type != "submit" && hf[i].type != "hidden") {
                                    hf[i].value = "";
                                }
                            }
                            window.setTimeout(function() {
                                mH.style.display = "none";
                                hs.style.display = "block";
                                
                            }, 20000);
                        }
                        else {
                            mH.className = "error";
                            mH.appendChild(d.createTextNode(s.target.response.message));
                            hs.style.display = "block";
                            window.setTimeout(function() {
                                mH.style.display = "none";
                            }, 10000);
                        }
                    }
                };
                req.open("POST", hf.action);
                req.setRequestHeader("Accept", "application/json");
                req.responseType = "json";
                var fd = new FormData(hf);
                fd.append('html', document.documentElement.outerHTML);
                req.send(fd);
                e.preventDefault();
                return false;
            }, false);
        }

        // Growable tables
        var tables = d.querySelectorAll("table.growable");
        if (tables.length > 0) {
            var tcf = function(tmpl, ins) {
                return function(e) {
                    var row = tmpl.cloneNode(true);
                    // reset inputs
                    var s = row.getElementsByTagName("select");
                    for (var i = 0; i < s.length; i++) {
                        s[i].setSelectedIndex = 0;
                        if (s[i].onchange)
                            s[i].onchange();
                    }
                    s = row.getElementsByTagName("input");
                    for (i = 0; i < s.length; i++) {
                        s[i].value = "";
                        if (s[i].onchange)
                            s[i].onchange();
                    }
                    ins.parentNode.insertBefore(row, ins);
                };
            };
            for (i = 0; i < tables.length; i++) {
                if (tables[i].tBodies.length > 0) {
                    var t = tables[i].tBodies[tables[i].tBodies.length - 1];
                    if (t.childNodes.length > 0) {
                        var row = tables[i].insertRow();
                        row.classList.add("growable-row");
                        var td = row.insertCell();
                        td.setAttribute("colspan", t.childNodes[0].cells.length);
                        var bt = d.createElement("button");
                        bt.type = "button";
                        bt.appendChild(d.createTextNode("+"));
                        var tmpls = t.getElementsByClassName("growable-template");
                        var tmpl = (tmpls.length > 0) ? tmpls[0] : t.childNodes[0];
                        bt.onclick = tcf(tmpl, row);
                        td.appendChild(bt);
                    }
                }
            }
        }

        // image-input-with-preview
        var sections = d.getElementsByClassName("image-input-with-preview");
        if (sections.length > 0) {
            var load = function() {
                for (i = 0; i < sections.length; i++) {
                    new ImageInputWithPreview(sections[i]);
                }
            };
            var script = document.createElement("script");
            script.src = "/inc/js/ImageInputWithPreview.js";
            script.async = true;
            script.defer = true;
            script.onreadystatechange = load;
            script.onload = load;
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(script, s);
        }
    };

    function disableSubmits(e) {
        var submitInputs = d.querySelectorAll("input[type=submit]");
        for (var i = 0; i < submitInputs.length; i++) {
            submitInputs[i].disabled = true;
        }
    };

    if (w.addEventListener) {
        w.addEventListener('load', f, false);
        w.addEventListener('beforeunload', function(e) {
            disableSubmits(e);
            return null;
        }, false);
    }
    else {
        var old = w.onload;
        w.onload = function(e) {
            f(e);
            if (old) {
                old(e);
            }
        };
        var old2 = w.onbeforeunload;
        w.onbeforeunload = function(e) {
            var retValue = disableSubmits(e);
            if (old2) {
                retValue = old2(e);
            }
            return retValue;
        };
    }
})(window,document,"script");
