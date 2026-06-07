(function () {
    'use strict';

    if (typeof window.Bale === 'undefined' || !window.Bale.WebApp) {
        return;
    }

    var wa = window.Bale.WebApp;
    wa.ready();
    wa.expand();

    document.documentElement.classList.add('sc-bale-miniapp');
})();
