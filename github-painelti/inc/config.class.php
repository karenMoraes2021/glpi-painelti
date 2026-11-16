<?php

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

/**
 * Configuração do Painel TI (guardada em glpi_configs, contexto "plugin:painelti").
 */
class PluginPaineltiConfig extends CommonDBTM
{
    public const CONTEXTO = 'plugin:painelti';

    /** Valores padrão */
    public const PADRAO = [
        'antivirus'       => '',  // parte do nome do antivírus padrão (vazio = qualquer antivírus)
        'antivirus_versao' => '', // versão alvo do antivírus (vazio = a maior versão encontrada no parque)
        'horas_prestes'   => 4,   // "prestes a estourar": vence nas próximas X horas
        'dias_sem_com'    => 7,   // "sem comunicação": agente sem contato há mais de X dias
        'aba_home_padrao' => 1,   // 1 = aba Painel TI em primeiro e aberta ao entrar na Home
    ];

    public static $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return 'Painel TI';
    }

    /** Todas as configurações, com os padrões para o que não foi salvo */
    public static function getAll(): array
    {
        static $cache = null;
        if ($cache === null) {
            $salvo = Config::getConfigurationValues(self::CONTEXTO);
            $cache = array_merge(self::PADRAO, array_intersect_key($salvo, self::PADRAO));
        }
        return $cache;
    }

    public static function get(string $chave)
    {
        return self::getAll()[$chave] ?? (self::PADRAO[$chave] ?? null);
    }

    public static function save(array $dados): void
    {
        $valores = [
            'antivirus'       => mb_substr(trim((string) ($dados['antivirus'] ?? '')), 0, 100),
            'antivirus_versao' => preg_match('/^[0-9A-Za-z._-]{1,40}$/', trim((string) ($dados['antivirus_versao'] ?? ''))) ? trim((string) $dados['antivirus_versao']) : '',
            'horas_prestes'   => max(1, min(72, (int) ($dados['horas_prestes'] ?? 4))),
            'dias_sem_com'    => max(1, min(365, (int) ($dados['dias_sem_com'] ?? 7))),
            'aba_home_padrao' => empty($dados['aba_home_padrao']) ? 0 : 1,
        ];
        Config::setConfigurationValues(self::CONTEXTO, $valores);
    }

    public static function install(): void
    {
        $atual = Config::getConfigurationValues(self::CONTEXTO);
        $faltando = array_diff_key(self::PADRAO, $atual);
        if ($faltando) {
            Config::setConfigurationValues(self::CONTEXTO, $faltando);
        }
    }

    public static function uninstall(): void
    {
        Config::deleteConfigurationValues(self::CONTEXTO, array_keys(self::PADRAO));
    }

    public static function showForm(): void
    {
        $c = self::getAll();
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        global $CFG_GLPI;
        $url = $CFG_GLPI['root_doc'] . '/plugins/painelti/front/config.php';
        ?>
        <form method="post" action="<?= $e($url) ?>">
          <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-layout-dashboard me-2"></i>Painel TI - configuração</h3></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="pti-av">Antivírus padrão (parte do nome)</label>
                  <input type="text" class="form-control" id="pti-av" name="antivirus" maxlength="100" value="<?= $e($c['antivirus']) ?>" placeholder="Ex.: Defender, Kaspersky, Cortex">
                  <div class="form-text">Como aparece no inventário (aba Antivírus do computador). Em branco: considera qualquer antivírus.</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="pti-h">"Prestes a estourar" (horas)</label>
                  <input type="number" class="form-control" id="pti-h" name="horas_prestes" min="1" max="72" value="<?= (int) $c['horas_prestes'] ?>">
                  <div class="form-text">Chamados com prazo de solução nas próximas X horas.</div>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="pti-d">"Sem comunicação" (dias)</label>
                  <input type="number" class="form-control" id="pti-d" name="dias_sem_com" min="1" max="365" value="<?= (int) $c['dias_sem_com'] ?>">
                  <div class="form-text">Agentes de inventário sem contato há mais de X dias.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="pti-avv">Versão alvo do antivírus</label>
                  <input type="text" class="form-control" id="pti-avv" name="antivirus_versao" maxlength="40" value="<?= $e($c['antivirus_versao']) ?>" placeholder="Ex.: 9.4.0.489">
                  <div class="form-text">Quem estiver nesta versão ou em uma mais nova aparece como atualizado. Em branco: a referência é a maior versão encontrada nos computadores.</div>
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input type="hidden" name="aba_home_padrao" value="0">
                    <input class="form-check-input" type="checkbox" id="pti-home" name="aba_home_padrao" value="1" <?= $c['aba_home_padrao'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="pti-home">Abrir a Home na aba Painel TI e mostrá-la em primeiro lugar</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-footer text-end">
              <button type="submit" name="update" value="1" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Salvar</button>
            </div>
          </div>
        <?php
        Html::closeForm();
    }
}
