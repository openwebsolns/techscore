//
// Transforms a plain text area for user e-mail addresses into a fancy,
// AJAX-powered, auto-complete enabled input element.
//
// @author Dayan Paez
// @version 2014-12-13
//

window.addEventListener('load', function(e) {
    var textArea = document.getElementById('user-select');
    if (!textArea || !XMLHttpRequest)
        return;

    // Transform into:
    //   DIV
    //     DIV
    //       INPUT[text]
    //     UL for suggestions
    //     UL for chosen

    var divWrap = document.createElement("div");
    divWrap.classList.add("user-select-wrapper");

    var divInputWrap = document.createElement("div");
    divInputWrap.classList.add("user-select-input-wrapper");
    divWrap.appendChild(divInputWrap);

    var emailInput = document.createElement("input");
    emailInput.classList.add("user-select-input");
    emailInput.type = "text";
    emailInput.setAttribute("placholder", "Search by e-mail or name");
    divInputWrap.appendChild(emailInput);

    var suggestionList = document.createElement("ul");
    suggestionList.classList.add("user-select-suggestion-list");
    divWrap.appendChild(suggestionList);

    var chosenList = document.createElement("ul");
    chosenList.classList.add("user-select-chosen-list");
    divWrap.appendChild(chosenList);

    emailInput.addEventListener('keyup', function(e) {
        var input = emailInput.value.trim();
        if (input.length > 2) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/users?q=' + encodeURIComponent(input), true);
            xhr.responseType = "json";
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var obj = xhr.response;
                    console.log(obj);
                }
            };
            xhr.send();
        }
    }, false);

    textArea.parentNode.replaceChild(divWrap, textArea);
}, false);
