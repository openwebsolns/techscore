/*
 * Adds a JS-backed "check/uncheck all" box to the specified element
 *
 * Given the inputs' name, look for other checkboxes in the same
 *  document with that name and toggle their state according to the
 *  state of a reference checkbox appended to the element ID provided.
 *
 * @author Dayan Paez, Openweb Solutions
 */

var SelectAllTableCheckboxes = function(referenceName, referenceId) {
    var myObj = this;
    this.reference = document.getElementById(referenceId);
    if (!this.reference) {
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

    // Append to reference ID
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
    var inputs = document.getElementsByTagName("input");
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
