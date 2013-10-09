(function() {
    if (window.screen.width < 800) {
        var c = document.cookie, m = null, s = null;
        if (/mobile=true/.test(c)) {
            m = document.createElement("meta");
            m.setAttribute("name", "viewport");
            m.setAttribute("content", "width=device-width,initial-scale=1,maximum-scale=1");
            s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(m, s);
            // provide option to go back?
        }
        else if (!/mobile=false/.test(c)) {
            m = document.createElement("script");
            m.src = "/inc/js/mobile-prompt.js";
            m.async = true;
            m.defer = true;
            s = document.getElementsByTagName("script")[0];
            s.parentNode.insertBefore(m, s);
        }
    }
})();
