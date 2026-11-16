<?php

/**
 * Painel TI - Assistência › Painel TI (e aba na Home)
 * @license GPLv3+
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

Session::checkLoginUser();
if (!PluginPaineltiMenu::canView()) {
    Html::displayRightError();
    return;
}

/** @var DBmysql $DB */
/** @var array $CFG_GLPI */
global $DB, $CFG_GLPI;

// ---------------------------------------------------------------- versão alvo do antivírus (campo no painel)
$podeEditarAlvo = Session::haveRight('config', UPDATE);
if ($podeEditarAlvo && isset($_POST['antivirus_versao_salvar'])) {
    // O GLPI 11 já valida o token CSRF do formulário antes de chegar aqui.
    $novoAlvo = trim((string) ($_POST['antivirus_versao'] ?? ''));
    if ($novoAlvo === '' || preg_match('/^[0-9A-Za-z._-]{1,40}$/', $novoAlvo)) {
        PluginPaineltiConfig::save(array_merge(PluginPaineltiConfig::getAll(), ['antivirus_versao' => $novoAlvo]));
    }
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/painelti/front/painel.php' . ($qs !== '' ? '?' . $qs : ''));
}

// ---------------------------------------------------------------- ajustes
// Ajustes em Configurar › Plugins › Painel TI
$ANTIVIRUS     = (string) PluginPaineltiConfig::get('antivirus');     // parte do nome do antivírus (vazio = qualquer)
$HORAS_PRESTES = (int) PluginPaineltiConfig::get('horas_prestes');    // "prestes a estourar": vence nas próximas X horas
$DIAS_SEM_COM  = (int) PluginPaineltiConfig::get('dias_sem_com');     // "sem comunicação": agente sem contato há mais de X dias
$AV_ROTULO     = $ANTIVIRUS !== '' ? $ANTIVIRUS : 'qualquer antivírus';
$ALVO_AV       = (string) PluginPaineltiConfig::get('antivirus_versao'); // versão alvo do antivírus (campo no painel ou na configuração; vazio = a maior encontrada)

// ---------------------------------------------------------------- helpers
function painelti_query(string $sql)
{
    global $DB;
    return method_exists($DB, 'doQuery') ? $DB->doQuery($sql) : $DB->query($sql);
}

/** Primeiro valor da consulta, ou null se der erro */
function painelti_val(string $sql)
{
    try {
        $res = painelti_query($sql);
        $row = $res ? $res->fetch_row() : null;
        return $row ? ($row[0] ?? 0) : 0;
    } catch (\Throwable $e) {
        Toolbox::logError('Painel TI: ' . $e->getMessage());
        return null;
    }
}

function painelti_rows(string $sql): array
{
    try {
        $res = painelti_query($sql);
        $out = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $out[] = $row;
        }
        return $out;
    } catch (\Throwable $e) {
        Toolbox::logError('Painel TI: ' . $e->getMessage());
        return [];
    }
}

function painelti_e($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Cartão do painel. $value null = erro na consulta */
function painelti_card(string $label, $value, string $icon, string $color, ?string $href = null, string $sub = ''): string
{
    $zero  = ($value === 0 || $value === '0' || $value === 0.0);
    $class = 'pti-card' . ($zero ? ' pti-zero' : '');
    $shown = ($value === null) ? '—' : painelti_e($value);
    $tag   = $href ? 'a' : 'div';
    $attr  = $href ? ' href="' . painelti_e($href) . '"' : '';
    return '<' . $tag . $attr . ' class="' . $class . '" style="--c:' . painelti_e($color) . '">'
        . '<span class="pti-label">' . painelti_e($label) . '</span>'
        . ($sub !== '' ? '<span class="pti-sub">' . painelti_e($sub) . '</span>' : '')
        . '<span class="pti-icon"><i class="' . painelti_e($icon) . '"></i></span>'
        . '<span class="pti-num">' . $shown . '</span>'
        . '</' . $tag . '>';
}

$root = $CFG_GLPI['root_doc'];
// embed=1: dentro da aba da Home (sem o menu do GLPI)
$embed = !empty($_GET['embed']);
$aqui  = $embed ? '?embed=1&' : '?';
$uid  = (int) Session::getLoginUserID();
$dbu  = new DbUtils();
$entT = $dbu->getEntitiesRestrictRequest('AND', 't');
$entC = $dbu->getEntitiesRestrictRequest('AND', 'c');

// Mesas (grupos que recebem chamados). Técnico vê só as mesas das quais é membro.
$veTodas    = PluginPaineltiMenu::veTodas();
$meusGrupos = array_map('intval', $_SESSION['glpigroups'] ?? []);
$soMinhas   = $veTodas ? '' : ' AND id IN (' . ($meusGrupos ? implode(',', $meusGrupos) : '0') . ')';
$mesas = painelti_rows("SELECT id, completename FROM glpi_groups WHERE is_assign = 1 AND id IN (SELECT groups_id FROM glpi_itilcategories WHERE groups_id > 0) $soMinhas ORDER BY completename");
$idsMesas = array_map(static fn($m) => (int) $m['id'], $mesas);
$mesa  = (int) ($_GET['mesa'] ?? 0);
$mesaNome = '';
foreach ($mesas as $m) {
    if ((int) $m['id'] === $mesa) {
        $mesaNome = $m['completename'];
    }
}
if ($mesaNome === '') {
    $mesa = 0;
}

// ---------------------------------------------------------------- chamados
// Escopo: uma mesa escolhida; ou, para o técnico, as mesas dele MAIS os chamados atribuídos a ele
// (inclusive os que ficaram sem mesa); quem vê tudo não tem filtro.
$escopo = '';
if ($mesa > 0) {
    $escopo = " AND EXISTS (SELECT 1 FROM glpi_groups_tickets gt WHERE gt.tickets_id = t.id AND gt.type = 2 AND gt.groups_id = $mesa)";
} elseif (!$veTodas) {
    $escopo = " AND (EXISTS (SELECT 1 FROM glpi_groups_tickets gt WHERE gt.tickets_id = t.id AND gt.type = 2 AND gt.groups_id IN (" . ($idsMesas ? implode(',', $idsMesas) : '0') . "))"
        . " OR EXISTS (SELECT 1 FROM glpi_tickets_users tu0 WHERE tu0.tickets_id = t.id AND tu0.type = 2 AND tu0.users_id = " . (int) Session::getLoginUserID() . "))";
}
$base = "FROM glpi_tickets t WHERE t.is_deleted = 0 $entT $escopo";
$temTec   = "EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.type = 2)";
$inicioMes = date('Y-m-01 00:00:00');

// Cada cartão de chamado = um filtro. O mesmo filtro gera o número e a lista ao clicar.
$filtros = [
    'afazer'       => ['A fazer', "t.status IN (1,2,3) AND NOT $temTec"],
    'atendimento'  => ['Em atendimento', "t.status IN (2,3) AND $temTec"],
    'pendentes'    => ['Pendentes', "t.status = 4"],
    'abertos'      => ['Abertos', "t.status IN (1,2,3,4)"],
    'meus'         => ['Meus', "t.status IN (1,2,3,4) AND EXISTS (SELECT 1 FROM glpi_tickets_users tu WHERE tu.tickets_id = t.id AND tu.type = 2 AND tu.users_id = $uid)"],
    'solucionados' => ['Solucionados no mês', "t.status IN (5,6) AND t.solvedate >= '$inicioMes'"],
    'estourados'   => ['SLA estourado', "t.status IN (1,2,3) AND t.time_to_resolve IS NOT NULL AND t.time_to_resolve < NOW()"],
    'prestes'      => ['Prestes a estourar', "t.status IN (1,2,3) AND t.time_to_resolve BETWEEN NOW() AND NOW() + INTERVAL $HORAS_PRESTES HOUR"],
    'semtec'       => ['Sem técnico (atendimento vencido)', "t.status IN (1,2,3) AND t.time_to_own IS NOT NULL AND t.time_to_own < NOW() AND NOT $temTec"],
    'semcat'       => ['Sem categoria', "t.status IN (1,2,3,4) AND t.itilcategories_id = 0"],
    'semmesa'      => ['Sem mesa', "t.status IN (1,2,3,4) AND NOT EXISTS (SELECT 1 FROM glpi_groups_tickets gx WHERE gx.tickets_id = t.id AND gx.type = 2)"],
];
$c = [];
foreach ($filtros as $chave => [, $onde]) {
    $c[$chave] = painelti_val("SELECT COUNT(*) $base AND $onde");
}

$satisf = painelti_rows("SELECT AVG(s.satisfaction) AS media, COUNT(*) AS qtd FROM glpi_ticketsatisfactions s JOIN glpi_tickets t ON t.id = s.tickets_id WHERE s.satisfaction IS NOT NULL AND s.date_answered >= '$inicioMes' AND t.is_deleted = 0 $entT"
    . $escopo);
$satMedia = ($satisf && $satisf[0]['qtd'] > 0) ? number_format((float) $satisf[0]['media'], 1, ',', '') : 0;
$satQtd   = $satisf ? (int) $satisf[0]['qtd'] : 0;

// Por mesa
$porMesa = painelti_rows("SELECT gt.groups_id,
        SUM(t.status IN (1,2,3) AND NOT $temTec) AS afazer,
        SUM(t.status IN (2,3) AND $temTec) AS atendimento,
        SUM(t.status = 4) AS pendentes,
        SUM(t.status IN (1,2,3) AND t.time_to_resolve IS NOT NULL AND t.time_to_resolve < NOW()) AS estourados
    FROM glpi_tickets t
    JOIN glpi_groups_tickets gt ON gt.tickets_id = t.id AND gt.type = 2
    WHERE t.is_deleted = 0 AND t.status IN (1,2,3,4) $entT
    GROUP BY gt.groups_id");
$porMesaIdx = [];
foreach ($porMesa as $r) {
    $porMesaIdx[(int) $r['groups_id']] = $r;
}

// ---------------------------------------------------------------- inventário
$pcs      = painelti_val("SELECT COUNT(*) FROM glpi_computers c WHERE c.is_deleted = 0 AND c.is_template = 0 AND c.is_dynamic = 1 $entC");
$agTotal  = painelti_val("SELECT COUNT(*) FROM glpi_agents");
$ag24     = painelti_val("SELECT COUNT(*) FROM glpi_agents WHERE last_contact >= NOW() - INTERVAL 1 DAY");
$agSemCom = painelti_val("SELECT COUNT(*) FROM glpi_agents WHERE last_contact < NOW() - INTERVAL $DIAS_SEM_COM DAY OR last_contact IS NULL");
$agPct    = ($agTotal) ? round($ag24 * 100 / $agTotal) . '%' : 0;

// ---------------------------------------------------------------- antivírus
$avLike = $DB->escape('%' . $ANTIVIRUS . '%');
$avRows = painelti_rows("SELECT c.id, c.name, a.antivirus_version AS versao, a.is_active AS ativo, a.id AS av
    FROM glpi_computers c
    LEFT JOIN glpi_itemantiviruses a ON a.items_id = c.id AND a.itemtype = 'Computer' AND a.is_deleted = 0 AND a.name LIKE '$avLike'
    WHERE c.is_deleted = 0 AND c.is_template = 0 AND c.is_dynamic = 1 $entC");
$porPc = [];
foreach ($avRows as $r) {
    $id = (int) $r['id'];
    if (!isset($porPc[$id])) {
        $porPc[$id] = ['tem' => false, 'versao' => '', 'ativo' => false, 'nome' => (string) ($r['name'] ?? '')];
    }
    if ($r['av'] !== null) {
        $porPc[$id]['tem'] = true;
        $v = trim((string) $r['versao']);
        if ($v !== '' && ($porPc[$id]['versao'] === '' || version_compare($v, $porPc[$id]['versao'], '>'))) {
            $porPc[$id]['versao'] = $v;
        }
        if ((int) $r['ativo'] === 1) {
            $porPc[$id]['ativo'] = true;
        }
    }
}
$ultima = '';
$dist   = [];
foreach ($porPc as $p) {
    if (!$p['tem']) {
        continue;
    }
    $v = $p['versao'] !== '' ? $p['versao'] : '(sem versão)';
    $dist[$v] = ($dist[$v] ?? 0) + 1;
    if ($p['versao'] !== '' && ($ultima === '' || version_compare($p['versao'], $ultima, '>'))) {
        $ultima = $p['versao'];
    }
}
uksort($dist, static fn($a, $b) => version_compare($b, $a));
$ultimaAuto = $ultima; // maior versão encontrada (referência quando não há versão alvo)
// Versão alvo (opcional): se informada, é a referência; senão, vale a maior versão encontrada no parque.
$ultima = $ALVO_AV !== '' ? $ALVO_AV : $ultima;
$avOk = static fn(string $v): bool => $v !== '' && $v !== '(sem versão)' && version_compare($v, $ultima, '>=');
$avCom = $avAtual = $avDesat = $avInativo = $avSem = 0;
foreach ($porPc as $p) {
    if (!$p['tem']) {
        $avSem++;
        continue;
    }
    $avCom++;
    if (!$p['ativo']) {
        $avInativo++;
    } elseif ($avOk($p['versao'])) {
        $avAtual++;
    } else {
        $avDesat++;
    }
}
$avPct = $avCom ? round($avAtual * 100 / max(1, count($porPc))) . '%' : 0;

// ---------------------------------------------------------------- links dos cartões
// Abre a lista do próprio GLPI (Chamados ou Computadores) com exatamente os itens do cartão.
// Até 80 itens: filtra pelos números (bate 100% com o cartão).
// Acima disso: usa os filtros equivalentes do GLPI (pode diferir em poucos itens).
$LIMITE_IDS = 80;
$urlIds = static function (string $pagina, array $ids) use ($root): string {
    $q = [];
    foreach (array_values($ids) as $i => $id) {
        $q[] = ($i > 0 ? "criteria[$i][link]=OR&" : '') . "criteria[$i][field]=2&criteria[$i][searchtype]=equals&criteria[$i][value]=" . (int) $id;
    }
    return $root . '/front/' . $pagina . '?' . implode('&', $q) . '&sort=2&order=DESC&reset=reset';
};
$urlCriterios = static function (array $criterios) use ($root, $mesa): string {
    if ($mesa > 0) {
        $criterios[] = [8, 'equals', $mesa];
    }
    $q = [];
    foreach ($criterios as $i => [$campo, $tipo, $valor]) {
        $q[] = "criteria[$i][link]=AND&criteria[$i][field]=$campo&criteria[$i][searchtype]=$tipo&criteria[$i][value]=" . rawurlencode((string) $valor);
    }
    return $root . '/front/ticket.php?' . implode('&', $q) . '&sort=2&order=DESC&reset=reset';
};
$nativo = [
    'afazer'       => [[12, 'equals', 'notold'], [5, 'contains', 'NULL']],
    'atendimento'  => [[12, 'equals', 'process']],
    'pendentes'    => [[12, 'equals', 4]],
    'abertos'      => [[12, 'equals', 'notold']],
    'meus'         => [[12, 'equals', 'notold'], [5, 'equals', 'myself']],
    'solucionados' => [[12, 'equals', 'old'], [17, 'morethan', 'BEGINMONTH']],
    'estourados'   => [[12, 'equals', 'notold'], [82, 'equals', 1]],
    'prestes'      => [[12, 'equals', 'notold'], [18, 'morethan', 'NOW'], [18, 'lessthan', $HORAS_PRESTES . 'HOUR']],
    'semtec'       => [[12, 'equals', 'notold'], [5, 'contains', 'NULL']],
    'semcat'       => [[12, 'equals', 'notold'], [7, 'contains', 'NULL']],
    'semmesa'      => [[12, 'equals', 'notold'], [8, 'contains', 'NULL']],
];
// Computadores de cada cartão de inventário/antivírus
$pcsDe = ['semcom' => [], 'avdesat' => [], 'avinativo' => [], 'avsem' => []];
foreach (painelti_rows("SELECT DISTINCT items_id FROM glpi_agents WHERE itemtype = 'Computer' AND items_id > 0 AND (last_contact < NOW() - INTERVAL $DIAS_SEM_COM DAY OR last_contact IS NULL)") as $r) {
    $pcsDe['semcom'][] = (int) $r['items_id'];
}
foreach ($porPc as $id => $p) {
    if (!$p['tem']) {
        $pcsDe['avsem'][] = $id;
    } elseif (!$p['ativo']) {
        $pcsDe['avinativo'][] = $id;
    } elseif (!$avOk($p['versao'])) {
        $pcsDe['avdesat'][] = $id;
    }
}
$abrir = function (string $chave) use ($filtros, $nativo, $pcsDe, $base, $LIMITE_IDS, $urlIds, $urlCriterios, $mesa): ?string {
    if (isset($filtros[$chave])) {
        $ids = array_column(painelti_rows("SELECT t.id $base AND {$filtros[$chave][1]} ORDER BY t.id DESC LIMIT " . ($LIMITE_IDS + 1)), 'id');
        if (!$ids) {
            return null;
        }
        return count($ids) <= $LIMITE_IDS ? $urlIds('ticket.php', $ids) : $urlCriterios($nativo[$chave]);
    }
    if (isset($pcsDe[$chave])) {
        $ids = $pcsDe[$chave];
        if (!$ids) {
            return null;
        }
        return count($ids) <= $LIMITE_IDS ? $urlIds('computer.php', $ids) : $root . '/plugins/painelti/front/painel.php?mesa=' . $mesa . '&lista=' . $chave . '#pti-lista';
    }
    return null;
};

// ---------------------------------------------------------------- lista ao clicar
$listaChave = (string) ($_GET['lista'] ?? '');
$listaTitulo = '';
$listaCab = [];
$listaLinhas = [];   // cada linha: [url, [colunas...]]
$estados = [1 => 'Novo', 2 => 'Em atendimento (atribuído)', 3 => 'Em atendimento (planejado)', 4 => 'Pendente', 5 => 'Solucionado', 6 => 'Fechado'];
$data = static fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
if (isset($filtros[$listaChave])) {
    [$listaTitulo, $onde] = $filtros[$listaChave];
    $listaCab = ['Nº', 'Título', 'Status', 'Mesa', 'Técnico', 'Prazo de solução'];
    foreach (painelti_rows("SELECT t.id, t.name, t.status, t.time_to_resolve,
            (SELECT GROUP_CONCAT(g.completename SEPARATOR ', ') FROM glpi_groups_tickets gt JOIN glpi_groups g ON g.id = gt.groups_id WHERE gt.tickets_id = t.id AND gt.type = 2) AS mesas,
            (SELECT GROUP_CONCAT(TRIM(CONCAT(IFNULL(u.firstname,''), ' ', IFNULL(u.realname,''))) SEPARATOR ', ') FROM glpi_tickets_users tu JOIN glpi_users u ON u.id = tu.users_id WHERE tu.tickets_id = t.id AND tu.type = 2) AS tecnicos
        $base AND $onde ORDER BY t.time_to_resolve IS NULL, t.time_to_resolve, t.id DESC LIMIT 300") as $r) {
        $atrasado = $r['time_to_resolve'] && strtotime($r['time_to_resolve']) < time() && in_array((int) $r['status'], [1, 2, 3], true);
        $listaLinhas[] = [$root . '/front/ticket.form.php?id=' . (int) $r['id'], [
            '#' . (int) $r['id'], $r['name'], $estados[(int) $r['status']] ?? $r['status'],
            $r['mesas'] ?? '', $r['tecnicos'] ?? '', [$data($r['time_to_resolve']), $atrasado],
        ]];
    }
} elseif ($listaChave === 'semcom') {
    $listaTitulo = 'Sem comunicação há mais de ' . $DIAS_SEM_COM . ' dias';
    $listaCab = ['Agente', 'Último contato', 'Dias'];
    foreach (painelti_rows("SELECT a.name, a.last_contact, a.itemtype, a.items_id, DATEDIFF(NOW(), a.last_contact) AS dias FROM glpi_agents a WHERE a.last_contact < NOW() - INTERVAL $DIAS_SEM_COM DAY OR a.last_contact IS NULL ORDER BY a.last_contact") as $r) {
        $url = ($r['itemtype'] === 'Computer' && $r['items_id']) ? $root . '/front/computer.form.php?id=' . (int) $r['items_id'] : null;
        $listaLinhas[] = [$url, [$r['name'], $data($r['last_contact']) ?: 'nunca', $r['dias'] ?? '']];
    }
} elseif (in_array($listaChave, ['avdesat', 'avinativo', 'avsem'], true)) {
    $listaTitulo = ['avdesat' => 'Antivírus desatualizado', 'avinativo' => 'Antivírus desativado', 'avsem' => 'Sem antivírus'][$listaChave];
    $listaCab = ['Computador', 'Versão'];
    foreach ($porPc as $id => $p) {
        $entra = ($listaChave === 'avsem' && !$p['tem'])
            || ($listaChave === 'avinativo' && $p['tem'] && !$p['ativo'])
            || ($listaChave === 'avdesat' && $p['tem'] && $p['ativo'] && !$avOk($p['versao']));
        if ($entra) {
            $listaLinhas[] = [$root . '/front/computer.form.php?id=' . $id, [$p['nome'], $p['versao'] ?: '—']];
        }
    }
    usort($listaLinhas, static fn($a, $b) => strnatcasecmp($a[1][0], $b[1][0]));
}

// ---------------------------------------------------------------- página
if ($embed) {
    // Página sem menu, para a aba da Home. Links de chamados/computadores abrem na janela inteira.
    if (method_exists('Html', 'popHeader')) {
        Html::popHeader('Painel TI');
    } else {
        Html::nullHeader('Painel TI');
    }
    echo '<base target="_top"><style>body{background:#f5f7fb!important;margin:0}.pti{padding-top:0;min-height:100vh}</style>';
} else {
    Html::header('Painel TI', '', 'helpdesk', 'PluginPaineltiMenu');
}
?>
<style>
.pti { --navy:#0a2f5c; --muted:#6b7785; --line:#e3e8ef; --card:#fff; --bg:#f5f7fb; --text:#3b4654; --line2:#f0f3f7; --trk:#eef2f7; --hover:#f5f8fc;
  padding: 8px 4px 32px; color:var(--text); }
.pti.pti-dark { --navy:#9cc7ff; --muted:#94a3b8; --line:#334155; --card:#1e293b; --bg:#0f172a; --text:#e2e8f0; --line2:#273449; --trk:#334155; --hover:#24324a; }
.pti-dark, .pti:fullscreen, .pti-embed { background:var(--bg); }
.pti-dark { border-radius:10px; padding:12px 12px 32px; }
.pti:fullscreen { overflow:auto; padding:16px 20px 32px; }
.pti-tools { display:flex; gap:4px; }
.pti-tools button { border:1px solid var(--line); background:var(--card); color:var(--muted); width:32px; height:32px; border-radius:6px;
  display:inline-flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; }
.pti-tools button:hover { color:var(--navy); border-color:var(--navy); }
.pti-dark .form-select { background-color:var(--card); color:var(--text); border-color:var(--line); }
.pti-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
.pti-top h2 { margin:0; font-size:1.35rem; color:var(--navy); font-weight:700; }
.pti-top form { display:flex; gap:8px; align-items:center; }
.pti-top select { min-width:220px; }
.pti-upd { font-size:12px; color:var(--muted); }
.pti h3 { font-size:.95rem; font-weight:600; color:var(--text); margin:22px 0 10px; }
.pti-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:14px; }
.pti-card { position:relative; display:block; height:88px; padding:12px 14px; border-radius:10px; background:var(--card);
  border:1px solid var(--line); box-shadow:0 2px 10px rgba(10,47,92,.06); color:var(--c); text-decoration:none !important;
  transition:transform .15s, box-shadow .15s; overflow:hidden; }
a.pti-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(10,47,92,.14); }
.pti-label { position:absolute; top:11px; left:14px; right:14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--c); }
.pti-sub { position:absolute; top:27px; left:14px; right:14px; font-size:10.5px; color:var(--muted); }
.pti-icon { position:absolute; left:14px; bottom:12px; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center;
  background:color-mix(in srgb, var(--c) 14%, var(--card)); color:var(--c); font-size:17px; }
.pti-num { position:absolute; right:14px; bottom:6px; font-size:2.6rem; line-height:1; color:var(--c); font-variant-numeric:tabular-nums; }
.pti-zero { background:#8f969f; border-color:#8f969f; box-shadow:none; }
.pti-zero .pti-label, .pti-zero .pti-num, .pti-zero .pti-sub { color:#fff; }
.pti-zero .pti-icon { background:#fff; color:#8f969f; }
.pti-panel { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:14px 16px; box-shadow:0 2px 10px rgba(10,47,92,.06); }
.pti-two { display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:14px; }
.pti-table { width:100%; border-collapse:collapse; font-size:13px; font-variant-numeric:tabular-nums; }
.pti-table th { text-align:right; font-size:11px; text-transform:uppercase; color:var(--muted); font-weight:600; padding:6px 8px; border-bottom:1px solid var(--line); }
.pti-table th:first-child, .pti-table td:first-child { text-align:left; }
.pti-table td { text-align:right; padding:7px 8px; border-bottom:1px solid var(--line2); color:var(--text); }
.pti-dark a:not(.pti-card) { color:#9cc7ff; }
.pti-table td.pti-bad { color:#e5484d; font-weight:700; }
.pti-bar { display:grid; grid-template-columns:120px 1fr 48px; gap:10px; align-items:center; font-size:13px; margin:6px 0; font-variant-numeric:tabular-nums; }
.pti-bar .trk { display:block; height:10px; background:var(--trk); border-radius:5px; overflow:hidden; }
.pti-bar .fil { display:block; height:100%; border-radius:5px; background:#1565c0; }
.pti-bar .fil.top { background:#22a06b; }
.pti-bar span:last-child { text-align:right; }
.pti-list { margin-top:22px; scroll-margin-top:70px; }
.pti-list .pti-table td, .pti-list .pti-table th { text-align:left; }
.pti-list tr.pti-row { cursor:pointer; }
.pti-list tr.pti-row:hover td { background:var(--hover); }
.pti-list .pti-close { float:right; font-size:13px; }
.pti-note { font-size:12px; color:var(--muted); margin-top:8px; }
</style>

<div class="pti<?= $embed ? ' pti-embed' : '' ?>" id="pti">
  <div class="pti-top">
    <h2>Painel TI<?= $mesaNome !== '' ? ' — ' . painelti_e($mesaNome) : '' ?></h2>
    <form method="get" target="_self">
      <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
      <select name="mesa" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0"><?= $veTodas ? 'Todas as mesas' : 'Minhas mesas' ?></option>
        <?php foreach ($mesas as $m): ?>
          <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $mesa ? ' selected' : '' ?>><?= painelti_e($m['completename']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="pti-upd">Atualizado às <?= date('H:i') ?> · recarrega a cada 5 min</span>
    </form>
    <div class="pti-tools">
      <button type="button" onclick="location.reload()" title="Atualizar agora"><i class="ti ti-refresh"></i></button>
      <button type="button" id="pti-tema" onclick="ptiTema()" title="Tema escuro / claro"><i class="ti ti-moon"></i></button>
      <button type="button" id="pti-cheia" onclick="ptiTelaCheia()" title="Tela cheia"><i class="ti ti-maximize"></i></button>
    </div>
  </div>

  <h3>Chamados</h3>
  <div class="pti-grid">
    <?= painelti_card('A fazer', $c['afazer'], 'ti ti-flag', '#e5484d', $abrir('afazer'), 'na mesa, sem técnico') ?>
    <?= painelti_card('Em atendimento', $c['atendimento'], 'ti ti-headset', '#22a06b', $abrir('atendimento')) ?>
    <?= painelti_card('Pendentes', $c['pendentes'], 'ti ti-player-pause', '#f59e0b', $abrir('pendentes')) ?>
    <?= painelti_card('Abertos', $c['abertos'], 'ti ti-inbox', '#1565c0', $abrir('abertos')) ?>
    <?= painelti_card('Meus', $c['meus'], 'ti ti-user', '#0ea5b7', $abrir('meus')) ?>
    <?= painelti_card('Solucionados no mês', $c['solucionados'], 'ti ti-circle-check', '#22a06b', $abrir('solucionados')) ?>
  </div>

  <h3>SLA</h3>
  <div class="pti-grid">
    <?= painelti_card('Estourados', $c['estourados'], 'ti ti-alarm-off', '#e5484d', $abrir('estourados'), 'prazo de solução') ?>
    <?= painelti_card('Prestes a estourar', $c['prestes'], 'ti ti-alarm', '#f59e0b', $abrir('prestes'), 'próximas ' . $HORAS_PRESTES . 'h') ?>
    <?= painelti_card('Sem técnico', $c['semtec'], 'ti ti-user-off', '#e5484d', $abrir('semtec'), 'atendimento vencido') ?>
    <?= painelti_card('Sem categoria', $c['semcat'], 'ti ti-tag-off', '#8e4ec6', $abrir('semcat')) ?>
    <?= painelti_card('Sem mesa', $c['semmesa'], 'ti ti-users-minus', '#8e4ec6', $abrir('semmesa'), 'nenhum grupo atribuído') ?>
    <?= painelti_card('Satisfação no mês', $satMedia, 'ti ti-star', '#22a06b', null, $satQtd . ' resposta(s)') ?>
  </div>

  <h3>Por mesa</h3>
  <div class="pti-panel">
    <table class="pti-table">
      <tr><th>Mesa</th><th>A fazer</th><th>Em atendimento</th><th>Pendentes</th><th>Estourados</th></tr>
      <?php foreach ($mesas as $m): $r = $porMesaIdx[(int) $m['id']] ?? null; ?>
        <tr>
          <td><a target="_self" href="<?= $aqui ?>mesa=<?= (int) $m['id'] ?>"><?= painelti_e($m['completename']) ?></a></td>
          <td><?= (int) ($r['afazer'] ?? 0) ?></td>
          <td><?= (int) ($r['atendimento'] ?? 0) ?></td>
          <td><?= (int) ($r['pendentes'] ?? 0) ?></td>
          <td class="<?= (int) ($r['estourados'] ?? 0) > 0 ? 'pti-bad' : '' ?>"><?= (int) ($r['estourados'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <h3>Inventário</h3>
  <div class="pti-grid">
    <?= painelti_card('Computadores', $pcs, 'ti ti-device-desktop', '#22a06b', $root . '/front/computer.php', 'inventariados') ?>
    <?= painelti_card('Comunicando (24h)', $agPct, 'ti ti-wifi', '#1565c0', null, $ag24 . ' de ' . (int) $agTotal) ?>
    <?= painelti_card('Sem comunicação', $agSemCom, 'ti ti-wifi-off', '#e5484d', $abrir('semcom'), 'há mais de ' . $DIAS_SEM_COM . ' dias') ?>
  </div>

  <h3>Antivírus (<?= painelti_e($AV_ROTULO) ?>)</h3>

  <?php if ($podeEditarAlvo): ?>
  <form method="post" target="_self" class="pti-alvo" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 12px">
    <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
    <label for="pti-alvo-in" style="font-size:13px;font-weight:600;color:var(--text)">Versão alvo do antivírus</label>
    <input type="text" id="pti-alvo-in" name="antivirus_versao" maxlength="40" value="<?= painelti_e($ALVO_AV) ?>" placeholder="<?= painelti_e($ultimaAuto !== '' ? 'Ex.: ' . $ultimaAuto : 'Ex.: 9.4.0.489') ?>" style="width:190px;padding:5px 9px;border:1px solid var(--line);border-radius:6px;background:var(--card);color:var(--text);font-size:13px">
    <button type="submit" name="antivirus_versao_salvar" value="1" style="padding:5px 14px;border:0;border-radius:6px;background:#1565c0;color:#fff;font-size:13px;cursor:pointer">Salvar</button>
    <span style="font-size:12px;color:var(--muted)"><?= $ALVO_AV !== '' ? 'Igual ou mais nova que esta conta como atualizada.' : 'Em branco: a referência é a maior versão encontrada (' . painelti_e($ultimaAuto !== '' ? $ultimaAuto : 'nenhuma') . ').' ?></span>
  </form>
  <?php endif; ?>
  <div class="pti-grid">
      <?= painelti_card('Instalado', $avCom, 'ti ti-shield-check', '#1565c0', null, 'de ' . count($porPc) . ' computadores') ?>
      <?= painelti_card($ALVO_AV !== '' ? 'Na versão alvo' : 'Na versão mais nova', $avPct, 'ti ti-shield-up', '#22a06b', null, $ultima !== '' ? $ultima : '') ?>
      <?= painelti_card('Desatualizados', $avDesat, 'ti ti-shield-exclamation', '#f59e0b', $abrir('avdesat')) ?>
      <?= painelti_card('Desativados', $avInativo, 'ti ti-shield-off', '#e5484d', $abrir('avinativo')) ?>
      <?= painelti_card('Sem antivírus', $avSem, 'ti ti-shield-x', '#e5484d', $abrir('avsem')) ?>
  </div>
    <div class="pti-panel" style="margin-top:14px">
      <strong style="font-size:13px;color:var(--text)">Versões instaladas</strong>
      <?php if (!$dist): ?>
        <div class="pti-note">Nenhum computador com <?= painelti_e($AV_ROTULO) ?> no inventário.</div>
      <?php else: $maior = max($dist); foreach ($dist as $v => $n): ?>
        <div class="pti-bar">
          <span><?= painelti_e($v) ?></span>
          <span class="trk"><span class="fil<?= $avOk($v) ? ' top' : '' ?>" style="width:<?= round($n * 100 / $maior) ?>%"></span></span>
          <span><?= (int) $n ?></span>
        </div>
      <?php endforeach; endif; ?>
      <div class="pti-note"><?= $ALVO_AV !== '' ? 'Verde = versão alvo (' . painelti_e($ALVO_AV) . ') ou superior.' : 'Verde = versão mais nova encontrada no parque.' ?> Clique nos cartões para ver os computadores.</div>
    </div>

  <?php if ($listaTitulo !== ''): ?>
  <div class="pti-list" id="pti-lista">
    <div class="pti-panel">
      <a class="pti-close" target="_self" href="<?= $aqui ?>mesa=<?= $mesa ?>">Fechar lista ✕</a>
      <strong style="font-size:14px;color:var(--navy)"><?= painelti_e($listaTitulo) ?><?= $mesaNome !== '' ? ' — ' . painelti_e($mesaNome) : '' ?> (<?= count($listaLinhas) ?>)</strong>
      <?php if (!$listaLinhas): ?>
        <div class="pti-note">Nada nesta lista.</div>
      <?php else: ?>
      <table class="pti-table" style="margin-top:10px">
        <tr><?php foreach ($listaCab as $h): ?><th><?= painelti_e($h) ?></th><?php endforeach; ?></tr>
        <?php foreach ($listaLinhas as [$url, $cols]): ?>
          <tr class="<?= $url ? 'pti-row' : '' ?>"<?= $url ? ' onclick="(window.top || window).location.href=\'' . painelti_e($url) . '\'"' : '' ?>>
            <?php foreach ($cols as $i => $col): ?>
              <?php if (is_array($col)): ?>
                <td class="<?= $col[1] ? 'pti-bad' : '' ?>"><?= painelti_e($col[0]) ?></td>
              <?php elseif ($i === 0 && $url): ?>
                <td><a href="<?= painelti_e($url) ?>"><?= painelti_e($col) ?></a></td>
              <?php else: ?>
                <td><?= painelti_e($col) ?></td>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
setTimeout(function () { location.reload(); }, 300000);
(function () {
    var box = document.getElementById('pti');
    function aplica(escuro) {
        box.classList.toggle('pti-dark', escuro);
        document.body.style.setProperty('background', escuro ? '#0f172a' : '', 'important');
        var i = document.querySelector('#pti-tema i');
        if (i) { i.className = escuro ? 'ti ti-sun' : 'ti ti-moon'; }
    }
    var salvo = false;
    try { salvo = localStorage.getItem('painelti_tema') === 'escuro'; } catch (e) {}
    aplica(salvo);
    window.ptiTema = function () {
        var escuro = !box.classList.contains('pti-dark');
        aplica(escuro);
        try { localStorage.setItem('painelti_tema', escuro ? 'escuro' : 'claro'); } catch (e) {}
    };
    // Dentro da aba da Home, a tela cheia é o quadro inteiro; na página normal, só o painel
    function alvo() { try { if (window.frameElement) { return window.frameElement; } } catch (e) {} return box; }
    function docCheio() { try { return window.top.document; } catch (e) { return document; } }
    window.ptiTelaCheia = function () {
        var d = docCheio();
        if (d.fullscreenElement) { d.exitFullscreen(); return; }
        var el = alvo();
        (el.requestFullscreen ? el.requestFullscreen() : Promise.reject()).catch(function () {
            if (box.requestFullscreen) { box.requestFullscreen(); }
        });
    };
    function icone() {
        var cheio = !!docCheio().fullscreenElement || !!document.fullscreenElement;
        var i = document.querySelector('#pti-cheia i');
        if (i) { i.className = cheio ? 'ti ti-minimize' : 'ti ti-maximize'; }
    }
    document.addEventListener('fullscreenchange', icone);
    try { window.top.document.addEventListener('fullscreenchange', icone); } catch (e) {}
})();
</script>
<?php
if ($embed) {
    if (method_exists('Html', 'popFooter')) {
        Html::popFooter();
    } else {
        Html::nullFooter();
    }
} else {
    Html::footer();
}
