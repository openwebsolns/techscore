(function(w, d) {
w.addEventListener('load', function() {
var list = d.getElementById("teams-list");
if (!list)
return;
var NAMES = [];
var ALPHA = {};
var ORIG = [];
var li, lb, nm;
for (var i = 0; i < list.childNodes.length; i++) {
li = list.childNodes[i];
lb = li.getElementsByTagName("label")[0];
nm = lb.textContent;
NAMES.push(nm);
ALPHA[nm] = li;
ORIG.push(li);
}
NAMES.sort();
var p = d.createElement("p");
list.parentNode.insertBefore(p, list);
var b = d.createElement("button");
b.type = "button";
p.appendChild(b);
var T = d.createTextNode("Sort alphabetically");
b.appendChild(T);
b.classList.toggle("sort-alpha");
b.onclick = function(e) {
while (list.childNodes.length > 0)
list.removeChild(list.childNodes[0]);
if (b.classList.contains("sort-alpha")) {
for (var i = 0; i < NAMES.length; i++) {
list.appendChild(ALPHA[NAMES[i]]);
}
T.nodeValue = "Sort by rank";
}
else {
for (var i = 0; i < ORIG.length; i++) {
list.appendChild(ORIG[i]);
}
T.nodeValue = "Sort alphabetically";
}
b.classList.toggle("sort-alpha");
};
}, false);
})(window, document);
