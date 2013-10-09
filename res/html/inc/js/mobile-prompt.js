(function(D) {
    var f = function(evt) {
        var d = D.createElement("div");
        d.setAttribute("id", "ms-screen");
        D.body.appendChild(d);

        var s = D.createElement("div");
        s.setAttribute("id", "ms-container");
        d.appendChild(s);

        var b = D.createElement("p");
        b.appendChild(D.createTextNode("Would you like to switch to the mobile-optimized version?"));
        s.appendChild(b);

        b = D.createElement("p");
        b.setAttribute("id", "ms-buttons");
        s.appendChild(b);
        var r = D.createElement("button");
        r.setAttribute("type", "button");
        r.appendChild(D.createTextNode("Switch"));
        b.appendChild(r);
        r.onclick = function(evt) {
            var m = D.createElement("meta");
            m.setAttribute("name", "viewport");
            m.setAttribute("content", "width=device-width,initial-scale=1,maximum-scale=1");
            s = D.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(m, s);

            D.cookie = "mobile=true;path=/;max-age=3153600";
            D.body.removeChild(d);
        };
        b.appendChild(D.createTextNode(" "));

        r = D.createElement("button");
        r.appendChild(D.createTextNode("No thanks"));
        b.appendChild(r);
        r.onclick = function(evt) {
            D.cookie = "mobile=false;path=/;max-age=3153600";
            D.body.removeChild(d);
        };

        b = D.createElement("p");
        b.setAttribute("id", "ms-message");
        b.appendChild(D.createTextNode("If you change your mind, erase your browser cookies."));
        s.appendChild(b);
    };
    if (window.addEventListener)
        window.addEventListener('load', f, false);
    else if (window.onload)
        window.onload = f;
})(document);
