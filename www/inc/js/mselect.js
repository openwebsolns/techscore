/**
 * Make more useful form objects out of multiple-selects
 *
 * @author OpenWeb Solutions, LLC
 */

function OWSMultSelect(elem, incSearch) {
    if (!(elem instanceof HTMLSelectElement) || !elem.multiple)
        return;

    var myObj = this;

    this.name = elem.name;
    this.fromElement = elem;
    this.fromElement.name = "";
    this.fromElement.ondblclick = function(evt) {
        myObj.promoteSelected();
    };
    this.wrapper = document.createElement("div");
    this.wrapper.setAttribute("class", "msel-wrapper");
    this.wrapper.setAttribute("title", "Move items from one list to another to choose.");
    // this.wrapper.style.display = "inline-block";
    this.fromElement.parentNode.insertBefore(this.wrapper, this.fromElement);
    if (elem.id) {
        this.wrapper.setAttribute("id", "msel-" + elem.id);
    }
    for (var i = 0; i < elem.classList.length; i++) {
        this.wrapper.classList.add("msel-" + elem.classList[i]);
    }

    // First cell
    var c = document.createElement("div");
    c.setAttribute("class", "msel-from-wrapper");
    this.wrapper.appendChild(c);

    var s = (this.fromElement.length > 10 || incSearch);
    if (s) {
        this.filteredOptions = [];
        this.fromSearch = document.createElement("input");
        this.fromSearch.setAttribute("class", "msel-search");
        this.fromSearch.style.display = "block";
        this.fromSearch.addEventListener('input', function(evt) {
            myObj.performFromSearch();
        }, false);
        this.fromSearch.addEventListener('keypress', function(evt) {
            if (evt.key === 'Enter') {
                if (myObj.filteredOptions.length === 1) {
                    myObj.promoteSelected();
                    myObj.fromSearch.value = '';
                    myObj.performFromSearch();
                }
                evt.preventDefault();
                return false;
            }
            return true;
        }, false);
        c.appendChild(this.fromSearch);
    }
    c.appendChild(this.fromElement);

    // Enable press-enter to promote
    this.fromElement.addEventListener('keyup', function(evt) {
        if (evt.key === 'Enter') {
            myObj.promoteSelected();
        }
    }, false);

    // Button cell
    var c2 = document.createElement("div");
    c2.setAttribute("class", "msel-button-wrapper");
    c2.style.verticalAlign = "middle";
    this.wrapper.insertBefore(c2, c);

    this.promoteButton = document.createElement("button");
    this.promoteButton.setAttribute("type", "button");
    this.promoteButton.setAttribute("class", "msel-button-promote");
    this.promoteButton.appendChild(document.createTextNode("↑"));
    this.promoteButton.onclick = function(evt) {
        myObj.promoteSelected();
    };
    c2.appendChild(this.promoteButton);

    this.demoteButton = document.createElement("button");
    this.demoteButton.setAttribute("type", "button");
    this.demoteButton.setAttribute("class", "msel-button-demote");
    this.demoteButton.appendChild(document.createTextNode("↓"));
    this.demoteButton.onclick = function(evt) {
        myObj.demoteSelected();
    };
    c2.appendChild(this.demoteButton);

    // Results cell
    c = document.createElement("div");
    c.setAttribute("class", "msel-to-wrapper");
    this.wrapper.insertBefore(c, c2);

    if (s && false) {
        this.toSearch = document.createElement("input");
        this.toSearch.setAttribute("class", "msel-search");
        this.toSearch.style.display = "block";
        this.toSearch.onkeyup = function(evt) {
            myObj.performToSearch();
        };
        c.appendChild(this.toSearch);
    }

    this.toElement = document.createElement("select");
    this.toElement.classList.add("msel-selected");
    for (var i = 0; i < this.fromElement.classList.length; i++)
        this.toElement.classList.add(this.fromElement.classList[i]);
    this.toElement.style.display = "block";
    this.toElement.style.height = "100%";
    this.toElement.style.width = this.fromElement.innerWidth + "px";
    this.toElement.ondblclick = function(evt) {
        myObj.demoteSelected();
    };
    this.toElement.addEventListener('keyup', function(evt) {
        if (evt.key === 'Enter') {
            myObj.demoteSelected();
        }
    }, false);
    c.appendChild(this.toElement);

    // Hidden Elements
    this.payload = document.createElement("div");
    this.payload.setAttribute("class", "msel-payload");
    c.appendChild(this.payload);

    this.payloadMap = {};
    this.fromMap = {};

    // Load any existing ones
    this.promoteSelected();
}

OWSMultSelect.prototype.performFromSearch = function() {
    var term = this.fromSearch.value.trim();
    var re = null;
    if (term.length > 0)
        re = new RegExp(term, "i");
    this.filteredOptions = [];
    for (var i = 0; i < this.fromElement.length; i++) {
        var opt = this.fromElement.item(i);
        if (!this.isSearchMatch(opt, re)) {
            opt.style.display = "none";
            opt.disabled = true;
        }
        else {
            opt.style.display = "";
            opt.disabled = false;
            this.filteredOptions.push(opt);
        }
    }
    if (this.filteredOptions.length === 1) {
        this.filteredOptions[0].selected = true;
    }
};

OWSMultSelect.prototype.performToSearch = function() {
    var term = this.toSearch.value.trim();
    var re = null;
    if (term.length > 0)
        re = new RegExp(term, "i");
    for (var i = 0; i < this.toElement.length; i++) {
        var opt = this.toElement.item(i);
        if (!this.isSearchMatch(opt, re)) {
            opt.style.display = "none";
        }
        else if (!("mselChosen" in opt.dataset) || opt.dataset.mselChosen != "1") {
            opt.style.display = "";
        }
    }
};

OWSMultSelect.prototype.isSearchMatch = function(opt, query) {
    if (!query) {
        return true;
    }
    if (query.test(opt.textContent)) {
        return true;
    }
    if (query.test(opt.dataset.mselFilter)) {
        return true;
    }
    return false;
};

OWSMultSelect.prototype.promoteSelected = function() {
    for (var i = 0; i < this.fromElement.length; i++) {
        var opt = this.fromElement.item(i);
        if (opt.selected) {
            this.promoteOption(opt);
        }
    }
    this.toElement.setAttribute("size", Math.max(2, this.toElement.length));
};

OWSMultSelect.prototype.promoteOption = function(opt) {
    if (!("mselChosen" in opt.dataset) || opt.dataset.mselChosen != "1") {
        this.toElement.appendChild(opt.cloneNode(true));
        opt.dataset.mselChosen = "1";
        opt.style.display = "none";
        var c = document.createElement("input");
        c.type = "hidden";
        c.name = this.name;
        c.value = opt.value;
        this.payload.appendChild(c);
        this.payloadMap[opt.value] = c;
        this.fromMap[opt.value] = opt;
    }
};

OWSMultSelect.prototype.demoteSelected = function() {
    for (var i = 0; i < this.toElement.length; i++) {
        this.demoteOption(this.toElement.item(i));
    }
};

OWSMultSelect.prototype.demoteOption = function(opt) {
    if (opt.selected) {
        this.toElement.removeChild(opt);
        this.payload.removeChild(this.payloadMap[opt.value]);
        this.fromMap[opt.value].dataset.mselChosen = "0";
        this.fromMap[opt.value].style.display = "";
    }
};


window.addEventListener('load', function(evt) {
    var selects = document.getElementsByTagName("select");
    var mults = [];
    var combos = [];
    for (var i = 0; i < selects.length; i++) {
        if (selects[i].multiple)
            mults.push(selects[i]);
        else if (selects[i].options.length > 10 && !selects[i].classList.contains("color-chooser") && !selects[i].classList.contains("finish_output") && !selects[i].classList.contains("boat-chooser") && !selects[i].classList.contains("no-mselect")) {
            combos.push(selects[i]);
        }
    }

    for (var i = 0; i < mults.length; i++) {
        new OWSMultSelect(mults[i]);
    }
    if (!('ows' in window)) {
        window.ows = {};
    }
    window.ows.comboboxSelects = combos.map(function(elem) {
        return new OWSComboboxSelect(elem);
    });
}, false);
