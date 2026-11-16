<?php

/**
 * Painel TI - plugin para GLPI
 * Painel de indicadores da TI: chamados, SLA, inventário e antivírus, com cartões clicáveis.
 *
 * @license GPLv3+
 */

define('PLUGIN_PAINELTI_VERSION', '1.1.0');
define('PLUGIN_PAINELTI_MIN_GLPI', '11.0.0');
define('PLUGIN_PAINELTI_MAX_GLPI', '11.0.99');

function plugin_init_painelti()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['painelti'] = true;

    if (!Plugin::isPluginActive('painelti')) {
        return;
    }

    // Assistência › Painel TI
    $PLUGIN_HOOKS['menu_toadd']['painelti'] = ['helpdesk' => 'PluginPaineltiMenu'];
    // Página de configuração (Configurar › Plugins › Painel TI)
    $PLUGIN_HOOKS['config_page']['painelti'] = 'front/config.php';
    // Aba "Painel TI" na página inicial (Home)
    Plugin::registerClass('PluginPaineltiCentral', ['addtabon' => ['Central']]);

    if (PluginPaineltiConfig::get('aba_home_padrao')) {
        // Painel TI como primeira aba da Home e aberta por padrão ao entrar
        $PLUGIN_HOOKS['add_javascript']['painelti'] = ['js/home.js'];
        $PLUGIN_HOOKS['post_init']['painelti']      = 'plugin_painelti_postinit';
    }
}

/** Ao entrar no GLPI, a Home abre na aba Painel TI (depois vale a última aba que a pessoa escolher) */
function plugin_painelti_postinit()
{
    if (!Session::getLoginUserID() || Session::getCurrentInterface() !== 'central') {
        return;
    }
    if (!class_exists('PluginPaineltiMenu') || !PluginPaineltiMenu::canView()) {
        return;
    }
    if (empty($_SESSION['painelti_aba_padrao'])) {
        $_SESSION['painelti_aba_padrao'] = true;
        if (method_exists('Session', 'setActiveTab')) {
            Session::setActiveTab('Central', 'PluginPaineltiCentral$1');
        }
    }
}

function plugin_version_painelti()
{
    return [
        'name'         => 'Painel TI',
        'version'      => PLUGIN_PAINELTI_VERSION,
        'author'       => 'Karen dos Santos Moraes',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/karenMoraes2021/glpi-painelti',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PAINELTI_MIN_GLPI,
                'max' => PLUGIN_PAINELTI_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_painelti_check_prerequisites()
{
    return true;
}

function plugin_painelti_check_config($verbose = false)
{
    return true;
}
