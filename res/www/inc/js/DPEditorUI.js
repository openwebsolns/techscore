/*
 * Extentions to DPEditor for buttons
 *
 */

DPEditor.prototype.getXMLHttpRequestObject = function() {
    var ref = null;
    if (window.XMLHttpRequest) {
        ref = new XMLHttpRequest();
    } else if (window.ActiveXObject) { // Older IE.
        ref = new ActiveXObject("MSXML2.XMLHTTP.3.0");
    }
    return ref;
};

/**
 * Initialize the UI (buttons)
 *
 * @param boolean allowUploads true to include AJAX file-upload when linking images
 */
DPEditor.prototype.uiInit = function(allowUploads) {
    this.myToolbar = this.newElement("div", {"class":"dpe-toolbar", "id":this.myID + "_toolbar"});
    this.myAllowUploads = allowUploads;

    var myObj = this;

    var butt, label;
    // HEADINGS
    butt = this.newElement("select", {"title":"Change the heading for structure"}, this.myToolbar);
    butt.onchange = function(evt) { myObj.changeBlock(this); };
    label = this.newElement("option", {"value":""}, butt);
    label.appendChild(document.createTextNode("[Set header]"));

    label = this.newElement("option", {"value":"h1"}, butt);
    label.appendChild(document.createTextNode("Heading 1"));

    label = this.newElement("option", {"value":"h2"}, butt);
    label.appendChild(document.createTextNode("Heading 2"));

    label = this.newElement("option", {"value":"h3"}, butt);
    label.appendChild(document.createTextNode("Heading 3"));

    // BOLD
    this.myToolbar.appendChild(document.createTextNode(" "));
    butt = this.newElement("button", {"type":"button", "title":"Strong (bold) text"}, this.myToolbar);
    label = this.newElement("strong", {}, butt);
    label.appendChild(document.createTextNode("B"));
    butt.onclick = function(evt) { myObj.insertOrWrap("*", "BOLD"); };

    // STRIKETHROUGH
    butt = this.newElement("button", {"type":"button", "title":"Deleted (strikethrough) text"}, this.myToolbar);
    label = this.newElement("del", {}, butt);
    label.appendChild(document.createTextNode("S"));
    butt.onclick = function(evt) { myObj.insertOrWrap("✂", "DELETE"); };

    // LINKS
    this.myToolbar.appendChild(document.createTextNode(" "));
    butt = this.newElement("button", {"type":"button", "title":"Insert link"}, this.myToolbar);
    label = this.newElement("a", {"href":"#"}, butt);
    label.appendChild(document.createTextNode("a"));
    butt.onclick = function(evt) { myObj.insertOrWrapResource("a", "link title"); };

    // EMAIL
    butt = this.newElement("button", {"type":"button", "title":"Insert email link"}, this.myToolbar);
    butt.appendChild(document.createTextNode("@"));
    butt.onclick = function(evt) { myObj.insertOrWrapResource("e", "Email"); };

    // IMAGE
    butt = this.newElement("button", {"type":"button", "title":"Insert image"}, this.myToolbar);
    label = this.newElement("img", {"src":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAJCAMAAAAxZj1mAAAAAXNSR0IArs4c6QAAAD9QTFRF////ZpnMmZmZmcz/ZmZmM2aZAGbMzMzMmcxmmcyZzMyZZpmZZplmmZlmM2ZmZpkzzMxmmczMZmaZZmYzAGaZ4iFv9AAAAAFiS0dEAIgFHUgAAAA3SURBVAjXY2BAAYwgwAwCIA4rK5QPkwEDdA47AzNQCZCE6mFlA0JWViCHi4uTi5OTi4mLE9UWACaDAOVPZdN9AAAAAElFTkSuQmCC", "alt":"img"}, butt);
    butt.onclick = function(evt) { myObj.insertImage(); };

    // LISTS
    this.myToolbar.appendChild(document.createTextNode(" "));
    butt = this.newElement("button", {"type":"button", "title":"Insert bulleted list"}, this.myToolbar);
    label = this.newElement("img", {"alt":"•_", "src":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMAgMAAAArG7R0AAAACVBMVEUAAGcAAAAzMzOg5MREAAAAAXRSTlMAQObYZgAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfbBw8PEhQ3HGmZAAAAFklEQVQI12NIYGBgCAgNZQDRIECADwCJDQUvKSvoNwAAAABJRU5ErkJggg=="}, butt);
    butt.onclick = function(evt) { myObj.insertList("ul"); };

    butt = this.newElement("button", {"type":"button", "title":"Insert bulleted list"}, this.myToolbar);
    label = this.newElement("img", {"alt":"1_", "src":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMAQMAAABsu86kAAAABlBMVEUAAAASGSVqwbOOAAAAAXRSTlMAQObYZgAAAB1JREFUCNdjcGBg8P/AACSBIIGBQR/MTkDiJjAAAGcbBb6Gsmt7AAAAAElFTkSuQmCC"}, butt);
    butt.onclick = function(evt) { myObj.insertList("ol"); };

    // TABLE
    this.myToolbar.appendChild(document.createTextNode(" "));
    butt = this.newElement("button", {"type":"button", "title":"Insert a table"}, this.myToolbar);
    label = this.newElement("img", {"alt":"table", "src":"data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAMCAMAAABcOc2zAAAAM1BMVEVAAAAMJE0eQ2wpZpFTco5Af6dJmMRzk65wtdmcrbyPsMexs69fyfy2yNWj0+vP4erm6eaHHisrAAAAAXRSTlMAQObYZgAAAGhJREFUCNdlzksOwzAIBNDEgPnYY3z/0zamajcZzeqBENf1ysjcWdmJ8cCec1lfJhYk+8Ba+RSBDC+YltYjRIO4wA4gJJz/EApXB2tBFwiFNta7FQB56kj/HiUCN+X7tzHcz7jq4/34B2J4Bao4kdcKAAAAAElFTkSuQmCC"}, butt);
    butt.onclick = function(evt) { myObj.insertTable(); };

    this.myWrapper.insertBefore(this.myToolbar, this.myElement);

    // ------------------------------------------------------------
    // Add a screen
    // ------------------------------------------------------------
    this.myScreen = this.newElement("div", {"class":"dpe-screen", "id":this.myID+"_screen"}, document.body);
    var css = {
	"position":"fixed",
	"width":"100%",
	"height":"100%",
	"background":"rgba(0,0,0,0.8)",
	"zIndex":100,
	"display":"none",
	"overflow":"auto",
	"top":0,
	"left":0
    };
    var rule;
    for (rule in css)
	this.myScreen.style[rule] = css[rule];

    var closeLink = this.newElement("p", {}, this.myScreen);
    closeLink.appendChild(document.createTextNode("Close"));
    closeLink.onclick = function(evt) { myObj.showDialog(false); };
    css = {
	"width":"40em",
	"textAlign":"right",
	"color":"#aaa",
	"margin":"2em auto 0.5em",
	"cursor":"pointer"
    };
    for (rule in css)
	closeLink.style[rule] = css[rule];

    this.myDialog = this.newElement("div", {"class":"dpe-dialog", "id":this.myID+"_dialog"}, this.myScreen);
    css = {
	"background":"#f2f2f2",
	"width":"40em",
	"margin":"0 auto",
	"borderRadius":"0.25em",
	"padding":"1em",
	"zIndex":101
    };
    for (rule in css)
	this.myDialog.style[rule] = css[rule];
};

DPEditor.prototype.showDialog = function(flag) {
    if (flag)
	this.myScreen.style.display = "block";
    else {
	this.myScreen.style.display = "none";
	while (this.myDialog.hasChildNodes())
	    this.myDialog.removeChild(this.myDialog.childNodes[0]);
    }
};

// ------------------------------------------------------------
// EDITING functions
// ------------------------------------------------------------

DPEditor.prototype.insertOrWrap = function(chr, cont) {
    var spos = this.myElement.selectionStart;
    var epos = this.myElement.selectionEnd;

    var prefix = this.myElement.value.substring(0, spos);
    var suffix = this.myElement.value.substring(epos);

    var select = true;
    if (spos != epos) {
	cont = this.myElement.value.substring(spos, epos);
	select = false;
    }

    // are we at the edge of a word?
    var lrep = "", rrep = "";
    var loff = 0;
    if (spos > 0 && this.myElement.value.charAt(spos - 1).match(/\B/) == null) {
	lrep = " ";
	loff = 1;
    }
    if (spos < this.myElement.value.length - 1 && this.myElement.value.charAt(epos).match(/\B/) == null) {
	rrep = " ";
    }
    this.myElement.value = prefix + lrep + chr + cont + chr + rrep + suffix;
    this.myElement.focus();
    if (select)
	this.myElement.setSelectionRange(spos + 1 + loff, spos + 1 + loff + cont.length);
    else {
	spos = spos + 2 + loff + cont.length;
	this.myElement.setSelectionRange(spos, spos);
    }
};

DPEditor.prototype.insertOrWrapResource = function(elem, title) {
    // All elements are wrapped as: {elem:URL,title}.
    // A selection will be used as the title for the resource
    var spos = this.myElement.selectionStart;
    var epos = this.myElement.selectionEnd;

    var prefix = this.myElement.value.substring(0, spos);
    var suffix = this.myElement.value.substring(epos);

    var cont = "URL";
    if (spos != epos) {
	title = this.myElement.value.substring(spos, epos);
	if (elem == 'e')
	    cont = title;
    }

    // are we at the edge of a word?
    var lrep = "", rrep = "";
    var loff = 0;
    if (spos > 0 && this.myElement.value.charAt(spos - 1).match(/\B/) == null) {
	lrep = " ";
	loff = 1;
    }
    if (epos < this.myElement.value.length - 1 && this.myElement.value.charAt(epos).match(/\B/) == null) {
	rrep = " ";
    }
    this.myElement.value = prefix + lrep + '{' + elem + ':' + cont + rrep + ',' + title + '}' + suffix;
    this.myElement.focus();

    // Always select the "URL" text
    this.myElement.setSelectionRange(spos + 2 + elem.length + loff, spos + 2 + elem.length + loff + cont.length);
};

DPEditor.prototype.insertList = function(type) {
    var pos = this.myElement.selectionStart;
    var bul = (type == 'ol') ? '+' : '-';
    this.myElement.value = this.myElement.value.substring(0, pos) + "\n\n  " + bul + " " + this.myElement.value.substring(pos);
    this.myElement.focus();
    this.myElement.setSelectionRange(pos + 6, pos + 6);
};

DPEditor.prototype.insertTable = function() {
    var length = this.myElement.value.length;
    // insert two new lines in front and up to two lines after to
    // insert the table as a "block" element
    var pos = this.myElement.selectionStart;
    var pre = "\n\n";
    if (pos == 0) pre = "";
    else if (pos == 1 && this.myElement.value.charAt(0) == "\n")
	pre = "\n";
    else {
	if (this.myElement.value.charAt(pos - 1) == "\n")
	    pre = "\n";
	if (this.myElement.value.charAt(pos - 2) == "\n")
	    pre = "";
    }
    var suf = "\n\n";
    if (pos <= length - 2 && this.myElement.value.charAt(pos + 1) == "\n")
	suf = "\n";
    else if (pos <= length - 3 && this.myElement.value.substring(pos + 1, pos + 2) == "\n\n")
	suf = "";

    this.myElement.value = (this.myElement.value.substring(0, pos) + pre +
		   "| Header 1 | Header 2 |\n---\n| Cell 1   | Cell 2   |" +
		   suf + this.myElement.value.substring(pos));
    this.myElement.focus();
    this.myElement.setSelectionRange(pos + pre.length + 2, pos + pre.length + 10);
};

DPEditor.prototype.changeBlock = function(block) {
    // get "block" by searching in reverse for the first newline
    var pos = this.myElement.selectionStart;
    var newline = this.myElement.value.substring(0, pos).lastIndexOf("\n");
    var prefix = this.myElement.value.substring(0, newline + 1);
    var suffix = this.myElement.value.substring(newline + 1);
    // remove any visage of heading and track how many removed
    var removed = 0;
    for (var i = 0; i < 3; i++) {
	if (suffix.charAt(0) == "*") {
	    suffix = suffix.substring(1);
	    removed++;
	}
    }
    if (suffix.charAt(0) == " ") {
	suffix = suffix.substring(1);
	removed++;
    }

    var extra = "";
    if (block.value == 'h1')
	extra = "* ";
    if (block.value == 'h2')
	extra = "** ";
    if (block.value == 'h3')
	extra = "*** ";
    block.selectedIndex = 0;
    this.myElement.value = prefix + extra + suffix;
    this.myElement.focus();
    this.myElement.setSelectionRange(pos + extra.length - removed, pos + extra.length - removed);
};

DPEditor.prototype.insertImage = function() {
    var myObj = this;
    
    // All elements are wrapped as: {elem:URL,title}.
    // A selection will be used as the title for the resource
    var spos = this.myElement.selectionStart;
    var epos = this.myElement.selectionEnd;

    var title = "";
    if (spos != epos)
	title = this.myElement.value.substring(spos, epos);

    var altBox = this.newElement("input", {"type":"text", "value":title});
    this.newFormEntry("Short description", altBox, this.myDialog);
    this.newElement("hr", {}, this.myDialog);

    var subBox;
    var urlBox = this.newElement("input", {"type":"text"});
    this.newFormEntry("(Option 1) URL", urlBox, this.myDialog);
    this.myImageFetchURL = "";
    this.myImageFetchCheck = this.newElement("img", {}, urlBox.parentNode);
    this.myImageFetchCheck.style.marginLeft = "1em";
    urlBox.onkeyup = function(evt) {
	var val = urlBox.value.trim();
	if (val == myObj.myImageFetchURL)
	    return;
	myObj.myImageFetchURL = val;
	// Attempt to fetch it
	if (myObj.myImageFetchTimeout)
	    window.clearTimeout(myObj.myImageFetchTimeout);
	myObj.myImageFetchTimeout = window.setTimeout(function() {
	    var xhr = myObj.getXMLHttpRequestObject();
	    xhr.open("GET", val, true);
	    xhr.onreadystatechange = function(e) {
		if (xhr.readyState != 4)
		    return;
		if (xhr.status == 200 || xhr.status == 302 || xhr.status == 301) {
		    subBox.disabled = false;
		    myObj.myImageFetchCheck.src = "/inc/img/s.png";
		    myObj.myImageFetchCheck.setAttribute("alt", "✓");
		    myObj.myImageFetchCheck.setAttribute("title", "Image found.");
		}
		else {
		    subBox.disabled = true;
		    myObj.myImageFetchCheck.src = "/inc/img/e.png";
		    myObj.myImageFetchCheck.setAttribute("alt", "X");
		    myObj.myImageFetchCheck.setAttribute("title", "Image cannot be found.");
		}
	    };
	    xhr.send();
	}, 1000);
    };

    var p = this.newElement("p", {"class":"submit"}, this.myDialog);
    subBox = this.newElement("button", {"type":"button", "disabled":"disabled"}, p);
    subBox.appendChild(document.createTextNode("Insert image"));
    subBox.onclick = function(evt) {
	var cont = urlBox.value.trim();
	if (cont.length == 0) {
	    alert("Empty URL specified. Please add a URL for the image.");
	    return;
	}

	var title = altBox.value.trim();
	if (title.length == 0)
	    title = "Image";

	// Check the URL for an image?

	var prefix = myObj.myElement.value.substring(0, spos);
	var suffix = myObj.myElement.value.substring(epos);

	// are we at the edge of a word?
	var lrep = "", rrep = "";
	var loff = 0;
	if (spos > 0 && myObj.myElement.value.charAt(spos - 1).match(/\B/) == null) {
	    lrep = " ";
	    loff = 1;
	}
	if (epos < myObj.myElement.value.length - 1 && myObj.myElement.value.charAt(epos).match(/\B/) == null) {
	    rrep = " ";
	}
	myObj.myElement.value = prefix + lrep + '{img:' + cont + rrep + ',' + title + '}' + suffix;
	myObj.myElement.focus();

	// Always select the "URL" text
	var pos = 5 + spos + loff + cont.length + 2 + title.length;
	myObj.myElement.setSelectionRange(pos, pos);
	myObj.showDialog(false);
    };
    
    this.showDialog(true);
};

DPEditor.prototype.newFormEntry = function(title, value, parent) {
    var elem = this.newElement("div");
    var span = this.newElement("span", {"class":"prefix"}, elem);
    span.appendChild(document.createTextNode(title));
    elem.appendChild(value);
    if (parent)
	parent.appendChild(elem);
    return elem;
};
