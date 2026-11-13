<?php

/**
 * Painel TI - instalação e desinstalação.
 * O plugin não cria tabelas: só guarda a configuração em glpi_configs.
 */

function plugin_painelti_install()
{
    PluginPaineltiConfig::install();
    return true;
}

function plugin_painelti_uninstall()
{
    PluginPaineltiConfig::uninstall();
    return true;
}
