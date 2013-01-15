/* Uses AJAX to search list of sailors andpopulate AA list */

/**
 * Creates a new searcher using the table with the given ID
 *
 * @param String id the ID of the table
 */
function AASearcher(id) {
    this.table = document.getElementById(id);
    if (!this.table)
        return;

    this.request = null; // the current XHR object
    this.timeout = null; // the current timeout
    this.prevQuery = null; // the previous query

    var myObj = this;

    var exp = document.createElement("p");
    exp.appendChild(document.createTextNode("Search for sailors to add by using the form below."));
    this.table.parentNode.insertBefore(exp, this.table.nextSibling);

    var elem = document.createElement("div");
    elem.classList.add("form-entry");
    this.table.parentNode.insertBefore(elem, exp.nextSibling);

    var sub = document.createElement("span");
    elem.appendChild(sub);
    sub.classList.add("form_h");
    sub.appendChild(document.createTextNode("Name:"));

    this.search = document.createElement("input");
    this.search.id = "name-search";
    this.search.type = "text";
    elem.appendChild(this.search);
    this.search.onkeyup = function(evt) {
        if (myObj.request != null)
            myObj.request.abort();
        if (myObj.timeout != null)
            window.clearTimeout(myObj.timeout);
        myObj.timeout = window.setTimeout(function() { myObj.doSearch(); }, 200);
    };

    this.results = document.createElement("ul");
    this.results.id = "aa-input";
    this.table.parentNode.insertBefore(this.results, elem.nextSibling);

    this.noSailorsMessage = document.createElement("li");
    this.noSailorsMessage.appendChild(document.createTextNode("No sailors match"));
    this.noSailorsMessage.classList.add("message");

    this.shortQueryMessage = document.createElement("li");
    this.shortQueryMessage.appendChild(document.createTextNode("At least 5 characters required."));
    this.shortQueryMessage.classList.add("message");
    this.results.appendChild(this.shortQueryMessage);
}

AASearcher.prototype.doSearch = function() {
    if (this.search.value == this.prevQuery)
        return;

    this.prevQuery = this.search.value;
    if (this.search.value.length < 5) {
        // Add warning
        while (this.results.childNodes.length > 0)
            this.results.removeChild(this.results.childNodes[0]);
        this.results.appendChild(this.shortQueryMessage);
        return;
    }
    
    var myObj = this;
    this.request = new XMLHttpRequest();
    this.request.open("GET", "/search?q=" + escape(this.search.value), true);
    this.request.setRequestHeader("Accept", "application/json");
    this.request.onreadystatechange = function() {
        if (myObj.request.readyState == 4 && myObj.request.status == 200)
            myObj.fillResults(myObj.request.response);
    };
    this.request.send();
};

AASearcher.prototype.fillResults = function(doc) {
    try {
        var res = window.JSON.parse(doc);
        while (this.results.childNodes.length > 0)
            this.results.removeChild(this.results.childNodes[0]);

        if (res.length == 0) {
            this.results.appendChild(this.noSailorsMessage);
            return;
        }

        var myObj = this;
        var promoteGen = function(li) {
            return function(evt) {
                myObj.promote(li);
            };
        };

        var li, elem;
        for (var i = 0; i < res.length; i++) {
            li = document.createElement("li");
            this.results.appendChild(li);

            elem = document.createElement("strong");
            elem.appendChild(document.createTextNode(res[i].first_name + " " + res[i].last_name));
            li.appendChild(elem);

            li.appendChild(document.createTextNode(" " + res[i].year + " (" + res[i].school + ")"));
            li.style.cursor = "pointer";

            li.onclick = promoteGen(li);
        }
    }
    catch (e) {
    }
};

AASearcher.prototype.promote = function(li) {
    alert(li);
};

var old = window.onload;
if (old) {
    window.onload = function() {
        old();
        new AASearcher('sailortable');
    };
}
else
    window.onload = function() {
        new AASearcher('sailortable');
    };

/*
      $this->PAGE->addContent($p = new XPort("New sailors"));
      $form->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
      $form->add(new FItem('Name:', $search = new XTextInput('name-search', "")));
      $search->set('id', 'name-search');
      $form->add($ul = new XUl(array('id'=>'aa-input'),
                               array(new XLi("No sailors.", array('class'=>'message')))));
*/
