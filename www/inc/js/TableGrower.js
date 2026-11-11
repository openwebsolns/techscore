/**
 * Extend a table by adding more rows similar to a template row.
 */
function TableGrower(table, options) {
    this.options = {
        templateClassname : null,
        growableRowClassname : 'growable-row',
        columnSize : null,
        growButton : null,
        autoIncrementInputNames : true,
        logLevel : 0
    };

    if (!(table instanceof HTMLTableElement)) {
        this.log("Provided element is not HTMLTableElement.", -1);
        this.log(table, -1);
        return;
    }
    this.table = table;
    for (var option in options) {
        if (option in this.options) {
            this.options[option] = options[option];
        }
    }
    this.log(this.options);

    this.numberAdded = 0;
    this.setup();
}

TableGrower.prototype.log = function(message, level) {
    if (!level) {
        level = 0;
    }
    if (level <= this.options.logLevel) {
        console.log(message);
    }
};

TableGrower.prototype.getTBody = function() {
    var tbody = null;
    for (var i = this.table.tBodies.length - 1; i >= 0; i--) {
        tbody = this.table.tBodies[i];
        if (tbody.childNodes.length > 0) {
            break;
        }
    }
    return tbody;
};

TableGrower.prototype.getColumnSize = function(tbody) {
    if (this.options.columnSize != null) {
        return this.options.columnSize;
    }
    return tbody.childNodes[0].cells.length;
};

TableGrower.prototype.getTemplateRow = function(tbody) {
    var tmpls = [];
    if (this.options.templateClassname != null) {
        tmpls = tbody.getElementsByClassName(this.options.templateClassname);
    }
    return (tmpls.length > 0) ? tmpls[0] : tbody.childNodes[0];
};

TableGrower.prototype.getGrowButton = function() {
    if (this.options.growButton != null) {
        return this.options.growButton;
    }
    var bt = document.createElement("button");
    bt.type = "button";
    bt.appendChild(document.createTextNode("+"));
    return bt;
};

TableGrower.prototype.setup = function() {
    var tbody = this.getTBody();
    if (tbody == null) {
        this.log("No tbody elements with rows in table.");
        return;
    }

    var myObj = this;
    this.templateRow = this.getTemplateRow(tbody).cloneNode(true);
    this.growableRow = this.table.insertRow();
    this.growableRow.classList.add(
        this.options.growableRowClassname
    );
    var td = this.growableRow.insertCell();
    td.setAttribute("colspan", this.getColumnSize(tbody));
    var bt = this.getGrowButton();
    bt.addEventListener(
        'click',
        function(e) {
            myObj.growTable();
        },
        false
    );
    td.appendChild(bt);
};

TableGrower.prototype.autoIncrementInputName = function(name) {
    var match = name.match(/\[(\d+)\]/);
    if (match != null) {
        var newNumber = Number(match[1]) + this.numberAdded + 1;
        var newName = name.replace(
            match[0],
            "[" + newNumber + "]"
        );
        this.log("From [" + name + "] to [" + newName + "]");
        return newName;
    }
};

TableGrower.prototype.resetInputs = function(row) {
    if (this.options.autoIncrementInputNames) {
        var s = row.getElementsByTagName("select");
        for (var i = 0; i < s.length; i++) {
            s[i].name = this.autoIncrementInputName(s[i].name);
        }
        s = row.getElementsByTagName("input");
        for (i = 0; i < s.length; i++) {
            s[i].name = this.autoIncrementInputName(s[i].name);
        }
    }
};

TableGrower.prototype.growTable = function() {
    var newRow = this.templateRow.cloneNode(true);

    this.resetInputs(newRow);
    this.growableRow.parentNode.insertBefore(
        newRow,
        this.growableRow
    );
    this.numberAdded++;
};
