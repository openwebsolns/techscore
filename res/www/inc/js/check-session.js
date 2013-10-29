/*
 * Binds a listener to submit buttons in POST forms to check if user is still logged-in
 * by issuing a HEAD request
 *
 * requires: XMLHttpRequest, FormData
 */

(function() {
    var TIMEOUT = 900000;
    var timer = Date.now();
    var cf = function(form, name, value) {
        return function(evt) {
            var now = Date.now();
            if (now - timer > TIMEOUT) {
                var inp = document.createElement("input");
                inp.type = "hidden";
                inp.name = name;
                inp.value = value;
                form.appendChild(inp);

                // Check
                var xml = new XMLHttpRequest();
                xml.onreadystatechange = function() {
	                  if (xml.readyState == 4) {
                        if (xml.status == 403) {
                            // Fetch login form
                            var login = new XMLHttpRequest();
                            login.onreadystatechange = function() {
                                if (login.readyState != 4)
                                    return;
                                if (login.status != 403) {
                                    form.submit();
                                    return;
                                }
                                var scr, wrp, exp;
                                var doc = login.responseXML;
                                var prt = doc.getElementById("login-port");
                                if (!prt) {
                                    form.submit();
                                    return;
                                }
                                prt = document.importNode(prt, true);
                                prt.style.margin = "0";

                                var fms = prt.getElementsByTagName("form");
                                if (fms.length == 0) {
                                    form.submit();
                                    return;
                                }
                                fms = fms[0];
                                fms.onsubmit = function(e2) {
                                    var post = new XMLHttpRequest();
                                    post.onreadystatechange = function() {
                                        if (post.readyState == 4) {
                                            if (post.status == 200) {
                                                form.submit();
                                            }
                                            else {
                                                while (exp.hasChildNodes())
                                                    exp.removeChild(exp.childNodes[0]);
                                                exp.appendChild(document.createTextNode(post.responseText));
                                            }
                                        }
                                    };
                                    post.open("POST", fms.action);
                                    post.setRequestHeader("API", "application/json");
                                    post.send(new FormData(fms));
                                    return false;
                                };

                                // Create screen
                                scr = document.createElement("div");
                                scr.style.position = "fixed";
                                scr.style.top = "0";
                                scr.style.left = "0";
                                scr.style.right = "0";
                                scr.style.bottom = "0";
                                scr.style.background = "rgba(0,0,0,0.8)";
                                scr.style.zIndex = 150;
                                document.body.appendChild(scr);

                                wrp = document.createElement("div");
                                wrp.style.maxWidth = "30em";
                                wrp.style.margin = "30px auto";
                                wrp.style.padding = "1em";
                                wrp.style.background = "white";
                                wrp.style.borderRadius = "0.5em";
                                scr.appendChild(wrp);

                                exp = document.createElement("p");
                                exp.appendChild(document.createTextNode("Your session has expired. To avoid losing your progress, please log in again."));
                                exp.className = "warning";
                                exp.style.marginTop = 0;
                                wrp.appendChild(exp);

                                wrp.appendChild(prt);
                            };
                            login.open('GET', '/');
                            login.send();
                        }
                        else {
                            form.submit();
                        }
                    };
                };
                xml.open('HEAD', '/');
                xml.send();
                return false;
            }
            return true;
        };
    };
    var forms = document.getElementsByTagName("form");
    var submitName, submitValue;
    for (var i = 0; i < forms.length; i++) {
        if (forms[i].method == "post" && forms[i].className != "no-check-session") {
            var inps = forms[i].getElementsByTagName("input");
            submitName = null, submitValue = null;
            for (var j = 0; j < inps.length; j++) {
                if (inps[j].type == "submit") {
                    submitName = inps[j].name;
                    submitValue = inps[j].value;
                    break;
                }
            }

            forms[i].onsubmit = cf(forms[i], submitName, submitValue);
        }
    }
})();
