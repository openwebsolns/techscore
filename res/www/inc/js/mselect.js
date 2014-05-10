/**
 * Make more useful form objects out of multiple-selects
 *
 * @author OpenWeb Solutions, LLC
 */

function OWSMultSelect(elem) {
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
    this.wrapper.style.display = "inline-block";
    this.fromElement.parentNode.insertBefore(this.wrapper, this.fromElement);

    // First cell
    var c = document.createElement("div");
    c.setAttribute("class", "msel-from-wrapper");
    this.wrapper.appendChild(c);

    var s = (this.fromElement.length > 10);
    if (s) {
        this.fromSearch = document.createElement("input");
        this.fromSearch.setAttribute("class", "msel-search");
        this.fromSearch.style.display = "block";
        this.fromSearch.onkeyup = function(evt) {
            myObj.performFromSearch();
        };
        c.appendChild(this.fromSearch);
    }
    c.appendChild(this.fromElement);

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
    for (var i = 0; i < this.fromElement.length; i++) {
        var opt = this.fromElement.item(i);
        if (re !== null && !re.test(opt.childNodes[0].nodeValue)) {
            opt.style.display = "none";
        }
        else if (!opt.selected) {
            opt.style.display = "";
        }
    }
};

OWSMultSelect.prototype.performToSearch = function() {
    var term = this.toSearch.value.trim();
    var re = null;
    if (term.length > 0)
        re = new RegExp(term, "i");
    for (var i = 0; i < this.toElement.length; i++) {
        var opt = this.toElement.item(i);
        if (re !== null && !re.test(opt.childNodes[0].nodeValue)) {
            opt.style.display = "none";
        }
        else if (!("mselChosen" in opt.dataset) || opt.dataset.mselChosen != "1") {
            opt.style.display = "";
        }
    }
};

OWSMultSelect.prototype.promoteSelected = function() {
    var cnt = 0;
    for (var i = 0; i < this.fromElement.length; i++) {
        var opt = this.fromElement.item(i);
        if (opt.selected) {
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
            cnt++;
        }
    }
    this.toElement.setAttribute("size", Math.max(2, this.toElement.length));
};

OWSMultSelect.prototype.demoteSelected = function() {
    for (var i = 0; i < this.toElement.length; i++) {
        var opt = this.toElement.item(i);
        if (opt.selected) {
            this.toElement.removeChild(opt);
            this.payload.removeChild(this.payloadMap[opt.value]);
            this.fromMap[opt.value].dataset.mselChosen = "0";
            this.fromMap[opt.value].style.display = "";
        }
    }
};



window.addEventListener('load', function(evt) {
    var selects = document.getElementsByTagName("select");
    var mults = [];
    var combos = [];
    for (var i = 0; i < selects.length; i++) {
        if (selects[i].multiple)
            mults.push(selects[i]);
        else if (selects[i].options.length > 10 && !selects[i].classList.contains("color-chooser") && !selects[i].classList.contains("finish_output") && !selects[i].classList.contains("boat-chooser")) {
            combos.push(selects[i]);
        }
    }

    for (var i = 0; i < mults.length; i++) {
        new OWSMultSelect(mults[i]);
    }
    for (var i = 0; i < combos.length; i++) {
        new OWSComboboxSelect(combos[i]);
    }
}, false);
