<?php

/**
 * Painel TI - página de configuração (Configurar › Plugins › Painel TI)
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    // O GLPI 11 já valida o token CSRF do formulário antes de chegar aqui.
    PluginPaineltiConfig::save($_POST);
    Session::addMessageAfterRedirect('Configuração do Painel TI salva.', false, INFO);
    Html::back();
}

Html::header('Painel TI', '', 'config', 'plugin');
PluginPaineltiConfig::showForm();
Html::footer();
