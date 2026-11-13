/* Painel TI: coloca a aba "Painel TI" em primeiro lugar na Home */
(function () {
    function primeiro() {
        var link = document.querySelector('a[href*="PluginPaineltiCentral"], a[data-bs-target*="PluginPaineltiCentral"]');
        if (!link) { return false; }
        var item = link.closest('li') || link;
        var lista = item.parentNode;
        if (lista && lista.firstElementChild !== item) {
            lista.insertBefore(item, lista.firstElementChild);
        }
        return true;
    }
    function tenta(n) {
        if (!primeiro() && n > 0) { setTimeout(function () { tenta(n - 1); }, 200); }
    }
    // Só age onde existe a aba (a Home); nas outras páginas não encontra o link e para
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { tenta(15); });
    } else {
        tenta(15);
    }
})();
