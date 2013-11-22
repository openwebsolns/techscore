(function (w,d) {
    w.addEventListener('load', function(e) {
        e = d.getElementById("last_updated");
        if (!e)
            return;
        var c = e.textContent.split("@");
        var t = new Date(c[0] + " " + c[1]);
        var n = (["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"])[t.getMonth()] + " " + t.getDate() + ", " + t.getFullYear() + " @ " + t.getHours() + ":" + t.getMinutes() + ":" + t.getSeconds();
        while (e.childNodes.length > 0)
            e.removeChild(e.childNodes[0]);
        e.appendChild(d.createTextNode(n));
    }, false);
})(window,document);
