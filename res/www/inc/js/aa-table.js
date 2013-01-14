/* Use JS to update a row based on a checkbox's checked status */

function initAaTable() {
    var tab = document.getElementById("regtable");
    if (!tab) {
        tab = document.getElementById("sailortable");
        if (!tab)
            return;
    }

    var inputs = tab.getElementsByTagName("input");
    var inp;
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked)
            inputs[i].parentNode.parentNode.classList.add("checked");
        inputs[i].onchange = function(evt) { this.parentNode.parentNode.classList.toggle("checked"); };
    }
}

var old = window.onload;
if (window.onload) {
    window.onload = function(evt) {
        old();
        initAaTable();
    };
}
else
    window.onload = initAaTable;
