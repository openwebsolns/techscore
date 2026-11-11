/**
 * Allow for re-ordering of table rows using the following classname structure.
 *
 *   - <tr class="{row_classname_filter}">   (only these will be sorted)
 *   - <td class="{drag_classname_filter}">  (only these can be used for sorting)
 *   - <input class="{order_input_classname_filter}">
 *         (update this input, rather than the first one in the row)
 *
 * Other options include:
 *
 *   - debug: "console" to write to console.
 */
function TableSorter(table, options) {
    this.table = table;
    this.options = {
        row_classname_filter         : options.row_classname_filter,
        drag_classname_filter        : options.drag_classname_filter,
        order_input_classname_filter : options.order_input_classname_filter,
        debug : options.debug
    };
    this.debug(this.options);

    this.init();
}

/**
 * Sets up the internal variables, such as the list of rows.
 *
 */
TableSorter.prototype.init = function() {
    var myObj = this;

    this.drag_from = null;
    this.drag_to = null;

    var rows = this.table.getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        if (!this.options.row_classname_filter || row.classList.contains(this.options.row_classname_filter)) {

            row.addEventListener(
                "mouseover",
                function(e) { myObj.onRowMouseOver(e); },
                false
            );

            row.addEventListener(
                "mouseout",
                function(e) { myObj.onRowMouseOut(e); },
                false
            );

            // Attach listener to appropriate cells
            var cells = row.childNodes;
            for (var j = 0; j < cells.length; j++) {
                var cell = cells[j];
                if (!this.options.drag_classname_filter || cell.classList.contains(this.options.drag_classname_filter)) {
                    cell.style.cursor = 'n-resize';
                    cell.addEventListener(
                        "mousedown",
                        function(e) { myObj.onCellMouseDown(e); },
                        false
                    );
                }
            }
        }
    }
};

TableSorter.prototype.getTargetRow = function(evt) {
    var targ;
    if (!evt) {
        evt = window.event;
    }

    if (evt.srcElement) {
	targ = evt.srcElement;
    } else if (evt.target) {
	targ = evt.target;
    }

    if (targ.nodeType == 3) { // defeat Safari bug
	targ = targ.parentNode;
    }

    return this.getParentNodeOfType(targ, "tr");
};

/**
 * Gets the firt parent node of given element that matches the given tagname.
 *
 * @param elem HTMLElement
 * @param tagname String
 * @return HTMLElement possibly the elem itself.
 * @throws Exception if none found.
 */
TableSorter.prototype.getParentNodeOfType = function(elem, tagname) {
    while (elem.nodeName.toLowerCase() != tagname) {
        elem = elem.parentNode;
        if (elem == null) {
            throw "No parent found with tagname=" + tagname;
        }
    }
    return elem;
};

/**
 * Callback on cell to start dragging.
 */
TableSorter.prototype.onCellMouseDown = function(evt) {
    this.drag_from = this.getTargetRow(evt);
    evt.preventDefault();
};

/**
 * Callback on row while dragging.
 */
TableSorter.prototype.onRowMouseOver = function(evt) {
    var targetRow = this.getTargetRow(evt);
    if (!this.drag_from) {
        this.debug("Not dragging.");
        return;
    }
    if (this.drag_from == targetRow) {
        this.debug("Not dropping on same row.");
        return;
    }

    this.drag_to = targetRow;
    this.drag_to.parentNode.insertBefore(this.drag_from, this.drag_to);

    // Change value of input fields
    this.updateOrderInputFieldValue(this.drag_to);
    this.updateOrderInputFieldValue(this.drag_from);
};

/**
 * Callback when mousing out of a row; resets target.
 *
 * @param evt Event object.
 */
TableSorter.prototype.onRowMouseOut = function(evt) {
    var targetRow = this.getTargetRow(evt);
    if (this.drag_to == targetRow) {
        this.drag_to = null;
    }
};

/**
 * Finds appropriate <input> field in row and updates its value based on row's index.
 *
 * @param row The <tr> element.
 */
TableSorter.prototype.updateOrderInputFieldValue = function(row) {
    var input = this.getOrderInputForRow(row);
    if (input) {
        input.value = this.nodeIndex(row);
    }
};

/**
 * Returns either the first <input> or the one with matching classname from options.
 *
 * @param row The <tr> element.
 * @return <input> or null.
 */
TableSorter.prototype.getOrderInputForRow = function(row) {
    var inputs = row.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++ ) {
        var input = inputs[i];
        if (!this.options.order_input_classname_filter
            || input.classList.contains(this.options.order_input_classname_filter)) {
            return input;
        }
    }
    return null;
};

/**
 * Gets the 1-based index of this node with respect to its parent.
 *
 * @param elem HTMLElement.
 * @throws Exception if not found in its parent.
 */
TableSorter.prototype.nodeIndex = function(elem) {
    for (var i = 0; i < elem.parentNode.childNodes.length; i++) {
	if (elem == elem.parentNode.childNodes[i]) {
	    return (i + 1);
        }
    }
    throw "Element not found among its parent";
};

TableSorter.prototype.debug = function(message) {
    if (this.options.debug == 'console') {
        console.log(message);
    }
};

/**
 * Apply the magic above to the following structure:
 *
 *   - <table class="tablesorter">        (enables sorting)
 *   - <tr class="tablesorter-sortable">  (only these will be sorted)
 *   - <td class="tablesorter-drag">      (the handle for sorting)
 *
 * In addition, add a helpful message to tables with classname:
 *
 *   - <table class="narrow">             (prints helpful message)
 */
(function(w, d) {
    var tablecname = "tablesorter";
    var narrowcname = "narrow";
    var debug = null; // "console";
    var options = {
        row_classname_filter : "tablesorter-sortable",
        drag_classname_filter : "tablesorter-drag",
        debug : debug
    };

    var getTotalCells = function(table) {
        var rows = table.getElementsByTagName("tr");
        for (var i = 0; i < rows.length; i++) {
            return rows[i].childNodes.length;
        }
        return 0;
    };

    var tables = d.getElementsByTagName("table");
    for (var i = 0; i < tables.length; i++) {
        var table = tables[i];
        if (table.classList.contains(tablecname)) {
            new TableSorter(table, options);
        }

        // Add message
        if (getTotalCells(table) < 4 || table.classList.contains(narrowcname)) {
            var message = document.createElement("span");
            table.parentNode.insertBefore(message, table.nextSibling);
            message.setAttribute("class", "message");
            message.appendChild(document.createTextNode("← Drag to change order"));
        }

        // Hide the first cell
        var rows = table.getElementsByTagName("tr");
        for (var j = 0; j < rows.length; j++) {
            rows[j].childNodes[0].style.display = "none";
        }
    }
})(window, document);
