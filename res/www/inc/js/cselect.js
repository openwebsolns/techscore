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
    this.element.style.display = "none";
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
    this.search.onblur = function(e) {
        myObj.validate();
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
    var cf = function(li) {
        return function(e) {
            myObj.choose(li);
            myObj.search.focus();
            myObj.validate();
        };
    };
    for (var i = 0; i < this.element.length; i++) {
        var opt = this.element.item(i);
        t = document.createElement("li");
        t.setAttribute("class", "csel-option");
        t.dataset.value = opt.value;
        t.dataset.option = opt.textContent;
        t.dataset.index = i;
        t.appendChild(document.createTextNode(opt.textContent));
        t.onclick = cf(t);
        this.options.appendChild(t);

        if (i == this.element.selectedIndex) {
            this.search.value = opt.textContent;
        }
    }

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
}

OWSComboboxSelect.prototype.filter = function() {
    this.search.classList.remove("invalid");
    var term = this.search.value.trim();
    var re = null;
    if (term.length > 0)
        re = new RegExp("^" + this.search.value, "i");
    var matches = [];
    for (var i = 0; i < this.options.childNodes.length; i++) {
        var opt = this.options.childNodes[i];
        if (re !== null && !re.test(opt.dataset.option)) {
            opt.style.display = "none";
        }
        else {
            opt.style.display = "";
            matches.push(opt);
        }
    }

    if (matches.length == 0)
        this.search.classList.add("invalid");
    else if (matches.length == 1) {
        /*
        var s = this.search.value.length;
        this.search.value = matches[0].dataset.option;
        this.search.setSelectionRange(s, this.search.value.length);
        this.search.focus();
         */
    }
};

OWSComboboxSelect.prototype.validate = function() {
    // this.search.setSelectionRange(this.search.selectionEnd, this.search.selectionEnd);
    this.search.classList.remove("invalid");
    this.hideOptions();

    var term = this.search.value.toLowerCase().trim();
    var found = null;
    for (var i = 0; i < this.options.childNodes.length; i++) {
        var opt = this.options.childNodes[i];
        if (opt.dataset.option.toLowerCase() == term) {
            found = opt.dataset.index;
            break;
        }
    }
    if (found) {
        this.element.selectedIndex = found;
    }
    else {
        this.element.selectedIndex = this.defaultSelectedIndex;
        this.search.value = this.options.childNodes[this.element.selectedIndex].dataset.option;
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

window.addEventListener('load', function(e) {
    var selects = document.getElementsByTagName("select");
    for (var i = 0; i < selects.length; i++) {
        if (!selects[i].multiple && selects[i].options.length > 10)
            new OWSComboboxSelect(selects[i]);
    }
}, false);
