/*
 * Determine whether the check-session.js script should be created at all
 *
 */

(function (w, d, n) {
    w.addEventListener('load', function(e) {
        if (!XMLHttpRequest || !FormData)
            return;
        var f = d.getElementsByTagName("form");
        for (var i = 0; i < f.length; i++) {
            if (f[i].method == "post" && f[i].className != "no-check-session") {
                var s = d.createElement(n);
                s.type = 'text/javascript';
                s.src = '/inc/js/check-session.js';
                d.head.appendChild(s);
                break;
            }
        }
    }, false);
})(window, document, "script");
