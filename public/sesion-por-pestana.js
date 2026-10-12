(function() {
    'use strict';
    const storagekey = 'casadets.tab_id';
    const validId = /^[a-f0-9]{32}$/;

    function crearidentificador() {
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);

        return Array.from(bytes, function(byte) {
            return byte.toString(16).padStart(2, '0');
        }).join('');
    }

    let tabId = window.sessionStorage.getItem(storagekey);

    if (!validId.test(tabId || '')){
        tabId = crearidentificador();
        window.sessionStorage.setItem(storagekey, tabId);
    } 

    window.CasadetsTab = Object.freeze({
        id: tabId,
    });
})();