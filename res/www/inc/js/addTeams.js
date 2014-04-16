/*
 * Transform new-teams pane into a user-friendly paradise.
 *
 */

window.addEventListener('load', function(e) {
    var t = document.getElementById("teams-list");
    if (!t)
        return;

    var load = function() {
        var x = document.getElementById("explanation");
        if (x) {
            x.appendChild(document.createTextNode(" Promote the school from the list on the left to the right. You may promote the school multiple times to indicate multiple teams."));
        }

        var s = document.createElement("select");
        var h = document.createElement("div");
        s.id = "teams-list-select";
        s.multiple = true;

        var numberInputMap = {};

        // Fill select with options from list
        var li, tm, grp, opt, inpSchool, inpNumber;
        for (var i = 0; i < t.childNodes.length; i++) {
            li = t.childNodes[i];
            grp = document.createElement("optgroup");
            grp.setAttribute("label", li.childNodes[0].textContent);
            s.appendChild(grp);
            for (var j = 0; j < li.childNodes[1].childNodes.length; j++) {
                tm = li.childNodes[1].childNodes[j];
                opt = document.createElement("option");
                opt.value = tm.childNodes[0].value;
                opt.appendChild(document.createTextNode(tm.childNodes[2].textContent));
                grp.appendChild(opt);

                inpSchool = document.createElement("input");
                inpSchool.type = "hidden";
                inpSchool.name = "school[]";
                inpSchool.value = tm.childNodes[0].value;

                inpNumber = document.createElement("input");
                inpNumber.type = "hidden";
                inpNumber.name = "number[]";
                inpNumber.value = 0;

                numberInputMap[inpSchool.value] = inpNumber;

                h.appendChild(inpSchool);
                h.appendChild(inpNumber);
            }
        }

        t.parentNode.insertBefore(h, t);
        t.parentNode.replaceChild(s, t);

        var m = new OWSMultSelect(s);
        m.wrapper.id = "teams-list-select-wrapper";
        m.wrapper.style.display = "table";
        m.toElement.style.height = "";

        m.promoteButton.removeChild(m.promoteButton.childNodes[0]);
        m.promoteButton.appendChild(document.createTextNode("→"));
        m.demoteButton.removeChild(m.demoteButton.childNodes[0]);
        m.demoteButton.appendChild(document.createTextNode("←"));

        m.promoteSelected = function() {
            for (var i = 0; i < m.fromElement.length; i++) {
                var opt = m.fromElement.item(i);
                if (opt.selected) {
                    this.toElement.appendChild(opt.cloneNode(true));
                    opt.dataset.mselChosen = "1";

                    // Add one to hidden element
                    var c = numberInputMap[opt.value];
                    c.value = Number(c.value) + 1;

                    this.payloadMap[opt.value] = c;
                    this.fromMap[opt.value] = opt;
                }
            }

        };

        m.demoteSelected = function() {
            for (var i = 0; i < m.toElement.length; i++) {
                var opt = m.toElement.item(i);
                if (opt.selected) {
                    m.toElement.removeChild(opt);
                    this.fromMap[opt.value].dataset.mselChosen = "0";
                    this.fromMap[opt.value].style.display = "";

                    var c = numberInputMap[opt.value];
                    c.value = Number(c.value) - 1;
                }
            }
        };
    };

    // Check up to 10 times for the presence of OWSMultSelect.
    var num = 0;
    var checkMultSelect = function() {
        if (!OWSMultSelect) {
            num++;
            if (num < 10)
                window.setTimeout(checkMultSelect, 100);
        }
        else {
            load();
        }
    };
    checkMultSelect();

}, false);
