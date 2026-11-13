<?php

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

/**
 * Aba "Painel TI" na página inicial (Home).
 * Mostra a mesma página de Assistência › Painel TI, sem o menu do GLPI, dentro da aba.
 */
class PluginPaineltiCentral extends CommonGLPI
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Central && Session::getCurrentInterface() === 'central' && PluginPaineltiMenu::canView()) {
            return '<i class="ti ti-layout-dashboard me-1"></i>Painel TI';
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;
        if (!($item instanceof Central) || !PluginPaineltiMenu::canView()) {
            return false;
        }
        $url = $CFG_GLPI['root_doc'] . '/plugins/painelti/front/painel.php?embed=1';
        echo '<iframe id="pti-frame" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" title="Painel TI" allowfullscreen'
            . ' style="width:100%;min-height:600px;border:0;display:block"></iframe>';
        echo '<style>#pti-frame:fullscreen{width:100%!important;height:100%!important;background:#f5f7fb}</style>';
        echo <<<'HTML'
<script>
(function () {
    var f = document.getElementById('pti-frame');
    if (!f) { return; }
    function ajusta() {
        try { f.style.height = (f.contentWindow.document.documentElement.scrollHeight + 10) + 'px'; } catch (e) {}
    }
    f.addEventListener('load', function () { ajusta(); setTimeout(ajusta, 500); });
    window.addEventListener('resize', ajusta);
})();
</script>
HTML;
        return true;
    }
}
