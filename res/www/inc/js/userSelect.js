//
// Transforms a plain text area for user e-mail addresses into a fancy,
// AJAX-powered, auto-complete enabled input element.
//
// @author Dayan Paez
// @version 2014-12-13
//

window.addEventListener('load', function(e) {
    var textArea = document.getElementById('user-select');
    if (!textArea || !OWSMultSelect)
        return;

    var TIMEOUT_FLAG = false;
    var TIMEOUT = 0; // ms

    // Transform into multiple dropdown, backed by AJAX search
    var select = document.createElement("select");
    select.name = "list[]";
    select.multiple = true;
    textArea.parentNode.replaceChild(select, textArea);

    var combo = new OWSMultSelect(select, true);
    combo.fromSearch.setAttribute("placeholder", "Search by name or email");
    var oldHandler = combo.fromSearch.onkeyup;
    combo.fromSearch.onkeyup = function(e) {
        var input = combo.fromSearch.value.trim();
        if (TIMEOUT_FLAG)
            return;

        if (input.length > 2) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/users?q=' + encodeURIComponent(input), true);
            xhr.responseType = "json";
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var users = xhr.response;
                    // Clear the field
                    while (combo.fromElement.length > 0)
                        combo.fromElement.remove(0);
                    for (var i = 0; i < users.length; i++) {
                        var option = document.createElement("option");
                        option.value = users[i].email;
                        option.appendChild(document.createTextNode(
                            users[i].first_name + " " + users[i].last_name
                            + " (" + users[i].email + ")"
                        ));
                        option.setAttribute("title", users[i].role);
                        combo.fromElement.appendChild(option);
                    }
                    window.setTimeout(function() {
                        TIMEOUT_FLAG = false;
                    }, TIMEOUT);
                    TIMEOUT_FLAG = true;
                    oldHandler();
                }
            };
            xhr.send();
        } else {
            while (combo.fromElement.length > 0)
                combo.fromElement.remove(0);
        }
    };
}, false);
