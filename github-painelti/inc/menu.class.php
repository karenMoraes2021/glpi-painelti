<?php

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginPaineltiMenu extends CommonGLPI
{
    public static $rightname = 'ticket';

    public static function getTypeName($nb = 0): string
    {
        return 'Painel TI';
    }

    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    public static function getIcon(): string
    {
        return 'ti ti-layout-dashboard';
    }

    /** Quem vê os chamados de todas as mesas, ou pelo menos os da própria mesa */
    public static function canView(): bool
    {
        return (bool) Session::haveRight('ticket', Ticket::READALL)
            || (bool) Session::haveRight('ticket', Ticket::READGROUP);
    }

    /** Vê todas as mesas? (Supervisor, Super-Admin) */
    public static function veTodas(): bool
    {
        return (bool) Session::haveRight('ticket', Ticket::READALL);
    }

    public static function getMenuContent()
    {
        if (!static::canView()) {
            return false;
        }
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/painelti/front/painel.php',
            'icon'  => self::getIcon(),
        ];
    }
}
