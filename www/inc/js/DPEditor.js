/*
 * A live-editor, based on contentEditable
 *
 * @author Dayan Paez
 * @version 2012-12-15
 */

/**
 * Creates a new four-way stack
 */
function DPContextMap() {
    this.env = [];
    this.buf = [];
    this.sym = [];
    this.arg = [];
}

DPContextMap.prototype.count = function() {
    return this.env.length;
};

DPContextMap.prototype.unshift = function(env, buf, sym, arg) {
    this.env.unshift((env) ? env : null);
    this.buf.unshift((buf) ? buf : "");
    this.sym.unshift((sym) ? sym : "");
    this.arg.unshift((arg) ? arg : 0);
};
DPContextMap.prototype.shift = function() {
    return [this.env.shift(), this.buf.shift(), this.sym.shift(), this.arg.shift()];
};

/**
 * Similar map for nested lists
 */
function DPList() {
    this.ul = [];
    this.li = [];
    this.sym = [];
}
DPList.prototype.count = function() { return this.ul.length; };

DPList.prototype.unshift = function(ul, li, sym) {
    this.ul.unshift((ul) ? ul : null);
    this.li.unshift((li) ? li : null);
    this.sym.unshift((sym) ? sym : "");
};

DPList.prototype.shift = function() {
    return [this.ul.shift(), this.li.shift(), this.sym.shift()];
};

/**
 * Creates a new editor
 *
 * @param String ID the ID of the textarea to editorize
 * @param boolean doPreview true to create the preview pane
 */
function DPEditor(id, doPreview) {
    this.myElement = document.getElementById(id);
    if (!this.myElement) {
	      this.log("No element with ID " + id);
	      return;
    }

    this.myID = id;

    // We strive towards XHTML compliance. As such, we expect the
    // source code to also be compliant, but that is the client's
    // job. For speed sake, we assume that the textarea can be wrapped
    // around a DIV precisely where it lives in the DOM.
    this.myContainer = this.newElement("div", {"class":"dpe-parent", "id":id + "_parent"});
    this.myElement.parentNode.replaceChild(this.myContainer, this.myElement);

    // Add wrapper
    this.myWrapper = this.newElement("div", {"class":"dpe-wrapper", "id":id + "_wrapper"}, this.myContainer);
    this.myWrapper.appendChild(this.myElement);

    // For backwards compatibility, assume preview requested
    this.myDisplay = null;
    if (doPreview == false) { return; }

    // templates
    this.oneast_tpl = this.newElement("h1");
    this.twoast_tpl = this.newElement("h2");
    this.thrast_tpl = this.newElement("h3");

    // figures class
    this.figure_class = null;

    this.myDisplay = this.newElement("div", {"class":"dpe-preview", "id":id + "_display"}, this.myContainer);
    var myObj = this;
    var listener = function(evt) { myObj.parse(); };
    this.myElement.onkeyup = listener;
    this.myElement.onfocus = listener;
    this.parse();
}

DPEditor.prototype.log = function(mes) {
    if (console.log) {
	      console.log("DPEditor: " + mes);
    }
};

/**
 * If a paragraph consists of ONLY images, then it will receive the special 'figure' class
 *
 * @param String cl the class (specify null to bypass feature)
 */
DPEditor.prototype.setFigureClass = function(cl) {
    this.figure_class = cl;
};

/**
 * Helper method to create elements
 *
 */
DPEditor.prototype.newElement = function(tag, attrs, parentNode) {
    var elem = document.createElement(tag);
    if (attrs) {
        for (var attr in attrs)
            elem.setAttribute(attr, attrs[attr]);
    }
    if (parentNode)
        parentNode.appendChild(elem);
    return elem;
};

/**
 * Return the HTMLElement object for the given resource tag.
 *
 * The resource tag is the element in {TAG:...[,...]}  environments.
 * This method allows subclasses to extend the list of such parsed
 * environments. The default understood values are 'img' (Ximg), 'a'
 * for (XA) and 'e', also for XA, with mailto: auto-prepended.
 *
 * When overriding this function, it is imperative to also override
 * the <pre>setResourceParam</pre> function as well.
 *
 * @param String tag alphanumeric string representing tag
 * @return HTMLElement|null null to indicate no tag recognized
 * @see setResourceParam
 */
DPEditor.prototype.getResourceTag = function(tag) {
    switch (tag) {
    case "a":
    case "e":
	      return this.newElement("a");
    case "img":
	      return this.newElement("img");
    default:
	      return null;
    }
};

/**
 * Set the resource's parameter number using provided content.
 *
 * @param int num either 0 or 1, at this point
 * @param HTMLElement env as returned by <pre>getResourceTag</pre>
 * @param String tag the tag used for the object
 * @param String cont the content to use
 * @param boolean close if this is the last argument
 * @see getResourceTag
 */
DPEditor.prototype.setResourceParam = function(num, env, tag, cont, close) {
    switch (tag) {
    case "a":
	      if (num > 0)
	          env.appendChild(document.createTextNode(cont));
	      else {
	          env.setAttribute("href", cont);
	          if (close)
		            env.appendChild(document.createTextNode(cont));
	      }
	      return;

    case "e":
	      if (num > 0)
	          env.appendChild(document.createTextNode(cont));
	      else {
	          env.setAttribute("href", "mailto:" + cont);
	          if (close)
		            env.appendChild(document.createTextNode(cont));
	      }
	      return;

    case "img":
	      if (num > 0)
	          env.setAttribute("alt", cont);
	      else {
	          env.setAttribute("src", cont);
	          if (close)
		            env.setAttribute("alt", "Image: " + cont);
	      }
	      return;

    default:
	      env.appendChild(document.createTextNode(cont));
    }
};

/**
 * Toggles parsing on/off (true/false) for resource.
 *
 * Some resources like images, use the second parameter for
 * attributes like alt-text, for which there is no inline
 * parsing. Others, like hyperlinks, allow inline elements to appear
 * as the second argument.
 *
 * @param int num the argument number
 * @param HTMLElement env the resource in question
 * @param String tag the tag used
 * @return boolean true if inline parsing should be allowed
 * @see getResourceTag
 * @see setResourceParam
 */
DPEditor.prototype.getParseForParam = function(num, env, tag) {
    return !(env instanceof HTMLImageElement);
};

DPEditor.prototype.parse = function(evt) {
    // empty the display div
    if (this.myDisplay == null) {
	      this.log("DPEditor: no display detected.");
	      return;
    }
    
    while (this.myDisplay.hasChildNodes())
	      this.myDisplay.removeChild(this.myDisplay.childNodes[0]);

    var input = this.myElement.value;
    if (this.preParse)
	      input = this.preParse(input);

    input += "\n\n";

    var context = new DPContextMap();
    var num_new_lines = 0;

    // inside certain environments, the parsing rules are relaxed. For
    // instance, in the first argument of an A element (href attr), or
    // in both arguments of an IMG element (src and alt attrs).
    var do_parse = true;

    // index to provide an auto-generated alt text to images
    var image_num = 0;

    // stack of previous list environments in nested fashion.  'sym'
    // contains both the depth and the symbol used, e.g. '   - '
    var lists = new DPList();

    // table rows must be kept in a queue until they are added either
    // to the head or the body of the table
    var trows = [];
    var table = null;
    var row = null;
    var td = null;
    var env = null;

    // gobble up characters
    var len = input.length;
    var i = 0;
    var chr, inlist, buf;
    while (i < len) {
	      chr = input[i];

	      // beginning of "new" environment
	      if (context.count() == 0) {
	          inlist = (lists.count() > 0 && num_new_lines == 1);

	          // ------------------------------------------------------------
	          // Headings
	          if (chr == "*" && !inlist) {
		            // gobble up to the first non-asterisk
		            buf = "";
		            while (++i < len && input[i] == "*")
		                buf += "*";
		            if (i < len && input[i] == " ") {
		                switch (buf.length) {
		                case 0:
			                  context.unshift(this.oneast_tpl.cloneNode()); break;
		                case 1:
			                  context.unshift(this.twoast_tpl.cloneNode()); break;
		                case 2:
			                  context.unshift(this.thrast_tpl.cloneNode()); break;
		                default:
			                  context.unshift(document.createElement("p"), buf);
		                }
		                lists = new DPList();
		                i++;
		                continue;
		            }
		            else
		                i--;
	          }

	          // ------------------------------------------------------------
	          // Tables
	          else if (chr == "|" && !inlist) {
		            lists = new DPList();

		            // are we already in a table
		            if (table == null) {
		                table = this.newElement("table");
		                trows = [];
		                this.myDisplay.appendChild(table);
		            }
		            // are we already in a row?
		            if (row == null) {
		                row = this.newElement("tr");
		                trows.push(row);
		            }

		            td = this.newElement("td", {}, row);
		            context.unshift(td);
		            i++;
		            continue;
	          }
	          else if (chr == '-' && table != null) {
		            // all previous rows belong in THEAD. All the cells thus far have been TD's,
		            // but they need to be converted to TH's.
		            env = this.newElement("thead");
		            table.appendChild(env);
		            for (var j = 0; j < trows.length; j++) {
		                for (var k = 0; k < trows[j].childNodes.length; k++) {
			                  td = this.newElement("th");
			                  while (trows[j].childNodes[k].hasChildNodes())
			                      td.appendChild(trows[j].childNodes[k].childNodes[0]);
			                  env.appendChild(td);
		                }
		            }
		            trows = [];
		            // consume until the end of the line
		            do { i++; } while (i < len && input[i] != "\n");
		            i++;
		            continue;
	          }

	          // ------------------------------------------------------------
	          // Lists. These are complicated, because they can be nested
	          // to any depth
	          // ------------------------------------------------------------
	          else if (chr == ' ') {
		            buf = ''; // depth
		            while (++i < len && input[i] == ' ')
		                buf += ' ';
		            if (i < len - 2) {
		                var sub = input.substring(i, i + 2);
		                if (sub == "- " || sub == "+ ") {
			                  var sym = (buf + sub);

			                  // if the previous environment is one of the lists,
			                  // then append this list item there. Recall that
			                  // we are more lenient with list items, allowing
			                  // one empty line between successive entries
			                  if (lists.count() == 0) {
			                      lists.unshift((sub == "- ") ? this.newElement("ul") : this.newElement("ol"), null, sym);
			                      this.myDisplay.appendChild(lists.ul[0]);
			                  }
			                  else if (lists.sym[0] == sym) {
			                      // most likely case: just another entry => do nothing here
			                  }
			                  else if (lists.sym[0].length < sym.length) {
			                      env = lists.li[0];
			                      lists.unshift((sub == "- ") ? this.newElement("ul") : this.newElement("ol"), null, sym);
			                      env.appendChild(lists.ul[0]);
			                  }
			                  else {
			                      // find the matching depth
			                      env = null;
			                      var j;
			                      for (j = 0; j < lists.count(); j++) {
				                        if (lists.sym[j] == sym) {
				                            env = lists.li[j];
				                            break;
				                        }
			                      }
			                      if (env != null) {
				                        for (var k = 0; k < j; k++)
				                            lists.shift();
			                      }
			                      else {
				                        // reverse compatibility: not actually a sublist,
				                        // but a misaligned -/+. Treat as regular text
				                        context.unshift(lists.li[0], (" " + sub), "", 0);
				                        i += 2;
				                        continue;
			                      }
			                  }

			                  context.unshift(this.newElement("li"));
			                  lists.ul[0].appendChild(context.env[0]);
			                  lists.li[0] = context.env[0];

			                  i += 2;
			                  continue;
		                }
		            }
		            i -= buf.length;
	          }
	          else if (chr == " " || chr == "\t") {
		            // trim whitespace
		            i++;
		            continue;
	          }
	      }

	      // ------------------------------------------------------------
	      // Table cell endings
	      // ------------------------------------------------------------
	      if (chr == "|" && context.env[0] instanceof HTMLTableCellElement) {
	          // are we at the end of a line? Let the new-line handler do it
	          if (i + 1 >= len || input[i + 1] == "\n") {
		            i++;
		            continue;
	          }

	          var cont = '';
	          for (j = context.count() - 1; j >= 0; j--)
		            cont += (context.sym[j] + context.buf[j]);
	          context.env[0].appendChild(document.createTextNode(cont.trimRight()));
	          context = new DPContextMap();
	          continue;
	      }

	      // ------------------------------------------------------------
	      // New lines? Are we at the end of some environment?
	      // ------------------------------------------------------------
	      if (chr == "\n") {
	          num_new_lines++;
	          num_envs = context.count();

	          if (num_envs > 0) {
		            env = context.env[num_envs - 1];

		            if (num_new_lines >= 2 || env instanceof HTMLLIElement || env instanceof HTMLTableCellElement) {
		                buf = '';
		                for (j = num_envs - 1; j >= 0; j--)
			                  buf += (context.sym[j] + context.buf[j]);
		                env.appendChild(document.createTextNode(buf.trimRight()));

		                if (!(env instanceof HTMLLIElement || env instanceof HTMLTableCellElement)) {
			                  this.myDisplay.appendChild(env);

			                  // ------------------------------------------------------------
			                  // Handle special 'figures' case
			                  // ------------------------------------------------------------
			                  if (this.figure_class != null && env instanceof HTMLParagraphElement) {
			                      var is_figure = true;
			                      for (j = 0; j < env.childNodes.length; j++) {
				                        if (env.childNodes[j] instanceof HTMLImageElement)
				                            continue;
				                        if (env.childNodes[j].nodeType == Node.TEXT_NODE && env.childNodes[j].nodeValue.trim() == "")
				                            continue;
				                        is_figure = false;
				                        break;
			                      }
			                      if (is_figure)
				                        env.setAttribute("class", this.figure_class);
			                  }
		                }
		                context = new DPContextMap();

		                if (env instanceof HTMLTableCellElement)
			                  row = null;
		            }
		            else // replace new line with space
		                context.buf[0] += " ";
	          }
	          // hard reset the list
	          if (num_new_lines >= 3)
		            lists = new DPList();

	          // hard reset the table
	          if (table != null && num_new_lines >= 2) {
		            var tbody = this.newElement("tbody", {}, table);
		            for (j = 0; j < trows.length; j++)
		                tbody.appendChild(trows[j]);
		            table = null;
	          }

	          i++;
	          continue;
	      }

	      // ------------------------------------------------------------
	      // Create a P element by default
	      // ------------------------------------------------------------
	      if (context.count() == 0) {
	          if (!inlist) {
		            context.unshift(this.newElement("p"));
		            lists = new DPList();
	          }
	          else {
		            context.unshift(lists.li[0], ' ');
	          }
	      }

	      // ------------------------------------------------------------
	      // At this point, we have an environment to work with, now
	      // consume characters according to inline rules
	      // ------------------------------------------------------------
	      if (chr == '\\' && (i + 1) < len) {
	          var next = input[i + 1];
	          var num_envs = context.count();
	          env = context.env[num_envs - 1];

	          if (next == "\n" && !(env instanceof HTMLTableCellElement)) {
		            buf = '';
		            for (j = num_envs - 1; j >= 0; j--)
		                buf += (context.sym[j] + context.buf[j]);
		            env.appendChild(document.createTextNode(buf.trimRight()));
		            this.newElement("br", {}, env);

		            // remove all but 'env'
		            while (context.count() > 1)
		                context.shift();
		            context.buf[0] = '';

		            i += 2;
		            continue;
	          }
	          // Escape commas inside {...} elements
	          else if (next == ",") {
		            context.buf[0] += next;
		            i += 2;
		            continue;
	          }
	      }
	      if (do_parse && (chr == "*" || chr == "✂")) {
	          // (possible) start of inline environment
	          //
	          // if not the first character, then previous must be word
	          // boundary; there must be a 'next' character, and it must be
	          // the beginning of a word; and it must not be the same
	          // character; and the environment must not already be in use
	          var a = context.buf[0];
	          if (context.sym.indexOf(chr) < 0
		            && (i + 1) < len
		            && input[i + 1] != chr
		            && input[i + 1] != " "
		            && input[i + 1] != "\t"
		            && (a == "" || /\B/.test(a.charAt(a.length - 1)))) {

		            env = null;
		            switch (chr) {
		            case "*": env = this.newElement("strong"); break;
		            case "✂": env = this.newElement("del"); break;
		            }
		            context.unshift(env, "", chr, 0);
		            i++;
		            continue;
	          }
	          // (possible) end of inline environment. Check if any inline
	          // environments in the stack are being closed, not just the
	          // top one. Viz:
	          //
	          //  Input: I *bought a /blue pony* mom.
	          // Output: I <strong>bought a /blue pony</strong> mom.
	          //
	          // It would be wrong to wait for the <em> to close before
	          // closing the <strong>
	          var closed = false;
	          for (j = 0; j < context.sym.length; j++) {
		            if (context.sym[j] == chr) {
		                closed = true;
		                break;
		            }
	          }
	          // do the closing by rebuilding j-th buffer with prior buffers
	          // (if any) and appending j-th to parent
	          if (closed) {
		            context.env[j].appendChild(document.createTextNode(context.buf[j]));
		            for (k = j - 1; k >= 0; k--) {
		                context.env[j].appendChild(document.createTextNode(context.sym[k]));
		                context.env[j].appendChild(document.createTextNode(context.buf[k]));
		            }
		            for (k = 0; k < j; k++)
		                context.shift();

		            // add myself to my parent and reset his buffer
		            context.env[1].appendChild(document.createTextNode(context.buf[1]));
		            context.env[1].appendChild(context.env[0]);
		            context.buf[1] = "";

		            context.shift();
		            i++;
		            continue;
	          }
	      } // end of */- inline

	      // ------------------------------------------------------------
	      // Opening {} environments
	      // ------------------------------------------------------------
	      if (do_parse && chr == "{") {
	          // Attempt to find the resource tag: alphanumeric characters
	          // followed by a colon (:), eg.g. "a:", "img:", etc
	          var colon_i = input.indexOf(":", i);
	          if (colon_i > (i + 1)) {
		            var tag = input.substring(i + 1, colon_i);
		            var xtag;
		            if (/^[A-Za-z0-9]+$/.test(tag) && (xtag = this.getResourceTag(tag)) != null) {
		                context.unshift(xtag, "", ("{" + tag + ":"), 0);
		                i += 2 + tag.length;
		                do_parse = false;
		                continue;
		            }
	          }
	      }

	      // ------------------------------------------------------------
	      // Closing {} environments?
	      // ------------------------------------------------------------
	      if (chr == "}") {
	          // see note about */- elements
	          closed = false;
	          for (j = 0; j < context.sym.length; j++) {
		            if (context.sym[j].length > 0 && context.sym[j][0] == "{") {
		                closed = true;
		                break;
		            }
	          }
	          // do the closing by rebuilding j-th buffer
	          if (closed) {
		            cont = context.buf[j];
		            for (k = j - 1; k >= 0; k--) {
		                cont += context.sym[k];
		                cont += context.buf[k];
		            }
		            for (k = 0; k < j; k++)
		                context.shift();

		            // set the second attribute depending on number of
		            // arguments for this environment
		            tag = context.sym[0].substring(1, context.sym[0].length - 1);
		            this.setResourceParam(context.arg[0], context.env[0], tag, cont, true);

		            // add myself to my parent and reset his buffer
		            context.env[1].appendChild(document.createTextNode(context.buf[1]));
		            context.env[1].appendChild(context.env[0]);
		            context.buf[1] = "";
		            context.shift();
		            i++;

		            do_parse = true;
		            continue;
	          }
	      } // end closing environments

	      // ------------------------------------------------------------
	      // commas are important immediately inside A's and IMG's, as
	      // they delineate between first and second argument
	      //
	      // The last condition limits two arguments per resource
	      // ------------------------------------------------------------
	      if (chr == "," && /^\{[A-Za-z0-9]+:$/.test(context.sym[0]) && context.arg[0] == 0) {
	          tag = context.sym[0].substring(1, context.sym[0].length - 1);
	          this.setResourceParam(0, context.env[0], tag, context.buf[0].trim(), false);
	          do_parse = this.getParseForParam(1, context.env[0], tag);
	          context.buf[0] = "";

	          i++;
	          context.arg[0] = 1;
	          continue;
	      }
	      
	      // ------------------------------------------------------------
	      // empty space at the beginning of block environment have no meaning
	      // ------------------------------------------------------------
	      if ((chr == " " || chr == "\t") && context.count() == 0 && context.buf[0] == "") {
	          i++;
	          continue;
	      }

	      // ------------------------------------------------------------
	      // Default action: append chr to buffer
	      // ------------------------------------------------------------
	      num_new_lines = 0;
	      context.buf[0] += chr;
	      i++;
    }
    // anything left: add it all
    
};
