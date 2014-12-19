/*
 * Adds a JS-backed "check/uncheck all" box to the specified element
 * of the table.
 *
 * Given a TH/TD HtmlElement, look for other checkboxes in the same
 *  column position of other rows in the table, and toggle their state
 *  whenever the reference element is toggled as well.
 *
 * @author Dayan Paez, Openweb Solutions
 */

var SelectAllTableCheckboxes = function(referenceName, referenceId) {
    var myObj = this;
    this.reference = document.getElementById(referenceId);

    // Validation
    if (!this.reference || !(this.reference instanceof HTMLTableCellElement)) {
        return;
    }

    // Find parent table
    this.table = this.reference.parentNode;
    while (this.table != null && !(this.table instanceof HTMLTableElement)) {
        this.table = this.table.parentNode;
    }
    if (!this.table) {
        return;
    }

    // Create master checkbox:
    //   SPAN.checkbox-span
    //     INPUT#chk-master
    //     LABEL
    var span = document.createElement("span");
    span.classList.add("checkbox-span");

    this.referenceName = referenceName;
    this.referenceBox = document.createElement("input");

    var idRoot = "chk-master";
    this.referenceBox.id = idRoot;
    var i = 1;
    while (document.getElementById(this.referenceBox.id)) {
        this.referenceBox.id = idRoot + i;
        i++;
    }
    this.referenceBox.type = "checkbox";
    span.appendChild(this.referenceBox);

    var label = document.createElement("label");
    label.setAttribute("for", this.referenceBox.id);
    span.appendChild(label);

    // Replace contents of table cell
    while (this.reference.childNodes.length > 0)
        this.reference.removeChild(this.reference.childNodes[0]);
    this.reference.appendChild(span);

    // Listeners
    this.referenceBox.addEventListener('change', function(e) {
        myObj.toggle();
    }, false);
};

/**
 * Get list of checkboxes in same position as reference
 *
 */
SelectAllTableCheckboxes.prototype.getCheckboxes = function() {
    var boxes = [];
    var inputs = this.table.getElementsByTagName("input");
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type == "checkbox" && inputs[i].name == this.referenceName)
            boxes.push(inputs[i]);
    }
    return boxes;
};

/**
 * Toggle all checkboxes based on value of referenceBox
 *
 */
SelectAllTableCheckboxes.prototype.toggle = function() {
    var isChecked = this.referenceBox.checked;
    var boxes = this.getCheckboxes();
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = isChecked;
    }
};
