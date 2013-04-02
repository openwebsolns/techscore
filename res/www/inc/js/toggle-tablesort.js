/*
 * Script to use toggle tablesort.js magic
 */

function makeButton(useNums) {
    var button = document.createElement("button");
    button.setAttribute("type", "button");
    if (useNums != false) {
        button.appendChild(document.createTextNode("Numbers"));
        button.onclick = function(evt) {
            destroyTableSort();
            button.parentNode.replaceChild(makeButton(false), button);
        };
    }
    else {
        button.appendChild(document.createTextNode("Drag-and-drop"));
        button.onclick = function(evt) {
            initTableSort();
            button.parentNode.replaceChild(makeButton(true), button);
        };
    }
    return button;
}

var old = window.onload;
if (old) {
    window.onload = function() {
        old();

        var p = document.createElement("p");
        TABLE.parentNode.insertBefore(p, TABLE);
        p.appendChild(document.createTextNode("Sort using:"));

        p.appendChild(makeButton(true));
    };
}
