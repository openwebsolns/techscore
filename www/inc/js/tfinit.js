/*
 * Initializes table filter for table(s) with class 'regatta-list'
 */

var old = window.onload;
window.onload = function(evt) {
    if (old)
        old(evt);

    var tables = document.getElementsByTagName("table");
    for (var t = 0; t < tables.length; t++) {
        if (tables[t].classList.contains('regatta-list'))
            new TableFilter(tables[t]);
    }
};
