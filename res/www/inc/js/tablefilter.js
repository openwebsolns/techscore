/*
 * Filters a table's rows based on header values
 */

/**
 * Create a new table filter for given table
 *
 */
function TableFilter(table) {
    this.table = table;
    var thead = table.getElementsByTagName("thead");
    var tbody = table.getElementsByTagName("tbody");
    if (tbody.length == 0 || thead.length == 0)
        return;

    this.rows = {};
    this.filteredRows = {};
    this.diffValues = {};
    this.appliedFilters = {};
    var rows = tbody[0].getElementsByTagName("tr");
    for (var r = 0; r < rows.length; r++) {
        rows[r].trID = r;
        this.filteredRows[r] = [];
        this.rows[r] = rows[r];
        for (var c = 0; c < rows[r].childNodes.length; c++) {
            if (!(c in this.diffValues)) {
                this.diffValues[c] = {};
            }
            var val = this.nodeValue(rows[r].childNodes[c]);
            if (val.length > 0) {
                if (!(val in this.diffValues[c]))
                    this.diffValues[c][val] = [];
                this.diffValues[c][val].push(rows[r]);
            }
        }
    }

    var header = thead[0].getElementsByTagName("tr");
    if (header.length == 0)
        return;
    header = header[header.length - 1];

    // Create dropdown for each header
    for (var i in this.diffValues) {
        var opts = this.diffValues[i];
        var len = this.dictLength(opts);
        if (header.childNodes.length > i && len > 1 && len < (rows.length / 2)) {
            var sel = document.createElement("select");
            sel.name = i;
            sel.classList.add("tr-filter");
            sel.onchange = this.onChangeFactory(sel);

            var opt;
            opt = document.createElement("option");
            opt.value = "";
            opt.appendChild(document.createTextNode("[All]"));
            sel.appendChild(opt);
            for (val in opts) {
                opt = document.createElement("option");
                opt.value = val;
                opt.appendChild(document.createTextNode(val));
                sel.appendChild(opt);
            }
            header.childNodes[i].appendChild(sel);
        }
    }
}

TableFilter.prototype.onChangeFactory = function(sel) {
    var myObj = this;
    return function(evt) {
        // Remove this filter from all rows
        for (var row in myObj.filteredRows) {
            var loc = myObj.filteredRows[row].indexOf(sel.name);
            if (loc >= 0)
                myObj.filteredRows[row].splice(loc, 1);
        }
        if (sel.name in myObj.appliedFilters)
            delete myObj.appliedFilters[sel.name];
        // Apply new one, as needed
        if (sel.value != "") {
            var rows = myObj.diffValues[sel.name][sel.value];
            for (var r = 0; r < rows.length; r++) {
                myObj.filteredRows[rows[r].trID].push(sel.name);
            }
            myObj.appliedFilters[sel.name] = 1;
        }
        myObj.updateRows();
    };
};

TableFilter.prototype.updateRows = function() {
    // If no filters applied, then SHOW ALL
    var r;
    var applied = this.dictLength(this.appliedFilters);
    if (applied == 0) {
        for (r in this.rows) {
            this.rows[r].style.display = "table-row";
        }
        return;
    }

    for (r in this.rows) {
        if (this.filteredRows[r].length != applied)
            this.rows[r].style.display = "none";
        else
            this.rows[r].style.display = "table-row";
    }
};

TableFilter.prototype.nodeValue = function(node) {
    var val = "";
    for (var i = 0; i < node.childNodes.length; i++) {
        switch (node.childNodes[i].nodeType) {
        case Node.TEXT_NODE:
            val += node.childNodes[i].nodeValue;
            break;
        case Node.ELEMENT_NODE:
            if (node.childNodes[i] instanceof HTMLImageElement)
                val += node.childNodes[i].alt;
            else
                val += this.nodeValue(node.childNodes[i]);
            break;
        }
    }
    return val;
};

TableFilter.prototype.dictLength = function(map) {
    var c = 0;
    for (var i in map)
        c++;
    return c;
};
