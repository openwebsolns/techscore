(function() {
    if (window.screen.width < 800) {
        var addMeta = function() {
            var m = document.createElement("meta");
            m.setAttribute("name", "viewport");
            m.setAttribute("content", "width=device-width,initial-scale=1,maximum-scale=1");
            var s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(m, s);
        };

        var c = document.cookie;
        if (/mobile=true/.test(c)) {
            addMeta();
            // provide option to go back?
        }
        else if (!/mobile=false/.test(c)) {
            var f = function(evt) {
                var d = document.createElement("div");
                d.setAttribute("id", "ms-screen");
                document.body.appendChild(d);

                var s = document.createElement("div");
                s.setAttribute("id", "ms-container");
                d.appendChild(s);

                var b = document.createElement("p");
                b.appendChild(document.createTextNode("Would you like to switch to the mobile-optimized version?"));
                s.appendChild(b);

                b = document.createElement("p");
                b.setAttribute("id", "ms-buttons");
                s.appendChild(b);
                var r = document.createElement("button");
                r.setAttribute("type", "button");
                r.appendChild(document.createTextNode("Switch"));
                b.appendChild(r);
                r.onclick = function(evt) {
                    addMeta();
                    document.cookie = "mobile=true;path=/;max-age=3153600";
                    document.body.removeChild(d);
                };
                b.appendChild(document.createTextNode(" "));

                r = document.createElement("button");
                r.appendChild(document.createTextNode("No thanks"));
                b.appendChild(r);
                r.onclick = function(evt) {
                    document.cookie = "mobile=false;path=/;max-age=3153600";
                    document.body.removeChild(d);
                };
            };
            if (window.addEventListener)
                window.addEventListener('load', f, false);
            else if (window.onload)
                window.onload = f;
        }
    }
})();
