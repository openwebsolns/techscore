/**
 * Allow typing in response for long drop down items
 *
 * @author OpenWeb Solutions, LLC
 */
function OWSComboboxSelect(elem) {
    if (!(elem instanceof HTMLSelectElement))
        return;

    var myObj = this;
    this.element = elem;
    this.defaultSelectedIndex = elem.selectedIndex;

    this.chosenIndex = -1;

    // Replace the select element with the following structure:
    //
    //   DIV .csel-container         {inline-block}
    //     DIV .csel-filter-wrapper  {table-row}
    //       INPUT .csel-filter      {table-cell}
    //       BUTTON .csel-drop       {table-cell}
    //     UL .csel-options          {auto}
    //       LI .csel-option         {auto}
    //
    // Each LI is an option from the original <select> element
    // which will be appended to .csel-container but hidden. The
    // data-value attribute will contain the original <option>
    // value attribute for easy retrieval.
    //
    // Typing in filter box will expand the list of options and
    // apply the appropriate filter, unless the key is the down
    // arrow in which case scroll through list of options
    //
    var c = document.createElement("div");
    c.setAttribute("class", "csel-container");
    c.style.display = "inline-block";
    c.style.width = this.element.innerWidth + "px";
    c.style.position = "relative";
    this.element.parentNode.insertBefore(c, this.element);
    this.element.style.visibility = "hidden";
    this.element.style.zIndex = -1;
    this.element.style.position = "absolute";
    this.element.removeAttribute("size");
    c.appendChild(this.element);

    var b = document.createElement("div");
    b.setAttribute("class", "csel-filter-wrapper");
    b.style.display = "table-row";
    c.appendChild(b);

    this.search = document.createElement("input");
    this.search.setAttribute("class", "csel-filter");
    this.search.style.display = "table-cell";
    this.search.oninput = function(e) {
        myObj.showOptions();
        myObj.filter();
    };
    this.search.onkeyup = function(e) {
        if (e.which == 40) {
            if (myObj.chooseFromFilter(myObj.chosenIndex + 1))
                myObj.chosenIndex++;
        }
        else if (e.which == 38) {
            if (myObj.chosenIndex > 0) {
                myObj.chosenIndex--;
                myObj.chooseFromFilter(myObj.chosenIndex);
            }
        }
        else if (e.which == 13) {
            myObj.validate();
            e.preventDefault();
            return false;
        }
        return true;
    };
    this.search.onkeypress = function(e) {
        if (e.which == 13) {
            e.preventDefault();
            return false;
        }
        return true;
    };
    b.appendChild(this.search);

    var t = document.createElement("span");
    t.setAttribute("class", "csel-drop");
    t.style.display = "table-cell";
    t.style.verticalAlign = "middle";
    t.appendChild(document.createTextNode("▾"));
    t.onclick = function(e) {
        myObj.toggleOptions();
    };
    b.appendChild(t);

    this.options = document.createElement("ul");
    this.options.setAttribute("class", "csel-options");
    this.options.style.display = "none";
    this.options.style.position = "absolute";
    this.options.style.zIndex = 3;
    c.appendChild(this.options);

    var num = 0;
    var sel = null;
    var addOption = function(opt, grp) {
        var t = document.createElement("li");
        t.setAttribute("class", "csel-option");
        t.dataset.value = opt.value;
        t.dataset.option = opt.textContent;
        t.dataset.index = num;
        t.appendChild(document.createTextNode(opt.textContent));
        t.onclick = function(e) {
            myObj.choose(t);
            myObj.search.focus();
            myObj.validate();
        };
        myObj.options.appendChild(t);

        if (grp) {
            var g = document.createElement("span");
            g.setAttribute("class", "csel-optgroup");
            g.appendChild(document.createTextNode(grp.label));
            t.appendChild(g);
        }

        if (opt.defaultSelected) {
            myObj.search.value = opt.textContent;
            sel = opt;
        }

        num++;
    };
    var grp, opt;
    for (var i = 0; i < this.element.childNodes.length; i++) {
        opt = this.element.childNodes[i];
        if (opt instanceof HTMLOptGroupElement) {
            grp = opt;
            for (var j = 0; j < grp.childNodes.length; j++) {
                addOption(grp.childNodes[j], grp);
            }
        }
        else {
            addOption(opt);
        }
    }

    // Select first element if none selected
    if (sel == null && num > 0) {
        myObj.search.value = this.element.options[0].textContent;
    }

    this.lastValidatedValue = this.search.value;
    this.clickedInEnvironment = false;
    c.addEventListener('click', function(e) {
        myObj.clickedInEnvironment = true;
    }, false);
    window.addEventListener('click', function(e) {
        if (myObj.clickedInEnvironment) {
            myObj.clickedInEnvironment = false;
        }
        else {
            myObj.validate();
        }
    }, false);
    c.addEventListener('keyup', function(e) {
        myObj.clickedInEnvironment = true;
    }, false);
    window.addEventListener('keyup', function(e) {
        if (myObj.clickedInEnvironment) {
            myObj.clickedInEnvironment = false;
        }
        else {
            myObj.validate();
        }
    }, false);
}

OWSComboboxSelect.prototype.filter = function() {
    this.search.classList.remove("invalid");
    var term = this.search.value.trim();
    var re = null;
    if (term.length > 0) {
        re = new RegExp("^" + this.search.value, "i");
    }

    var matches = [];
    for (var i = 0; i < this.options.childNodes.length; i++) {
        var opt = this.options.childNodes[i];
        if (re) {
            if (!re.test(opt.dataset.option)) {
                opt.style.display = "none";
            }
            else {
                opt.style.display = "";
                matches.push(opt);
            }
        }
        else {
            opt.style.display = "";
        }
    }

    if (!re)
        this.hideOptions();
    else if (matches.length == 0)
        this.search.classList.add("invalid");
    else if (matches.length == 1) {
        matches[0].classList.add("chosen");
    }
};

OWSComboboxSelect.prototype.validate = function() {
    if (this.search.value == this.lastValidatedValue)
        return;

    // this.search.setSelectionRange(this.search.selectionEnd, this.search.selectionEnd);
    this.search.classList.remove("invalid");
    this.hideOptions();

    var term = this.search.value.toLowerCase().trim();
    var exact = null;
    var near = [];
    for (var i = 0; i < this.options.childNodes.length; i++) {
        var opt = this.options.childNodes[i];
        var val = opt.dataset.option.toLowerCase();
        if (val == term) {
            exact = opt;
            break;
        }
        if (val.indexOf(term) == 0) {
            near.push(opt);
        }
    }
    if (exact) {
        this.search.value = exact.dataset.option;
        this.lastValidatedValue = this.search.value;
        this.element.selectedIndex = exact.dataset.index;
        this.element.onchange();
    }
    else if (near.length == 1) {
        this.search.value = near[0].dataset.option;
        this.lastValidatedValue = this.search.value;
        this.element.selectedIndex = near[0].dataset.index;
        this.element.onchange();
    }
    else {
        this.element.selectedIndex = this.defaultSelectedIndex;
        this.search.value = this.options.childNodes[this.element.selectedIndex].dataset.option;
        this.lastValidatedValue = this.search.value;
        // @TODO: warning about invalid value
    }
};

OWSComboboxSelect.prototype.showOptions = function() {
    this.options.style.display = "";
};

OWSComboboxSelect.prototype.hideOptions = function() {
    this.options.style.display = "none";
    this.chosenIndex = -1;
    for (var i = 0; i < this.options.childNodes.length; i++) {
        this.options.childNodes[i].classList.remove("chosen");
    }
};

OWSComboboxSelect.prototype.toggleOptions = function() {
    if (this.options.style.display == "none")
        this.showOptions();
    else
        this.hideOptions();
};

OWSComboboxSelect.prototype.choose = function(opt) {
    this.element.selectedIndex = opt.dataset.index;
    this.search.value = opt.dataset.option;
};

OWSComboboxSelect.prototype.chooseFromFilter = function(index) {
    var found = false;
    var activeOptions = [];
    var i, opt;
    for (i = 0; i < this.options.childNodes.length; i++) {
        if (this.options.childNodes[i].style.display != "none")
            activeOptions.push(this.options.childNodes[i]);
    }
    if (index >= activeOptions.length) {
        return false;
    }
    var num = 0;
    for (i = 0; i < activeOptions.length; i++) {
        opt = activeOptions[i];
        if (num == index) {
            opt.classList.add("chosen");
            // Scroll parent?
            var scrollDiff = (opt.offsetTop + opt.offsetHeight) - (opt.parentNode.scrollTop + opt.parentNode.offsetHeight);
            if (scrollDiff > 0) {
                opt.parentNode.scrollTop += scrollDiff;
            }
            else if (opt.offsetTop < opt.parentNode.scrollTop) {
                opt.parentNode.scrollTop = opt.offsetTop;
            }
            this.choose(opt);
        }
        else
            opt.classList.remove("chosen");
        num++;
    }
    return true;
};
