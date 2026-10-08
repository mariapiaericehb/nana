<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// ---------- Modulo appuntamento ----------
$eid = (int)get('evento');
if ($eid || get('nuovo')) {
    $e = $eid ? one('SELECT * FROM eventi WHERE id=?', [$eid]) : ['titolo' => '', 'tipo' => get('personale') ? 'personale' : 'appuntamento', 'cliente_id' => (int)get('cliente_id') ?: null,
        'data' => date_or_null(get('data')) ?? date('Y-m-d'), 'ora_inizio' => '', 'ora_fine' => '', 'luogo' => '', 'note' => '', 'ripeti' => 'no', 'ripeti_fino' => ''];
    if (!$e) redirect(url('calendario'));
    layout_start($eid ? 'Modifica appuntamento' : 'Nuovo appuntamento', 'calendario');
    page_head($eid ? 'Modifica appuntamento' : 'Nuovo appuntamento');
    ?>
    <form method="post" action="<?= url('azioni') ?>" class="card form narrow">
      <?= csrf() ?><input type="hidden" name="azione" value="evento_salva"><input type="hidden" name="id" value="<?= $eid ?>">
      <input type="hidden" name="_back" value="<?= h(url('calendario', ['d' => $e['data']])) ?>">
      <div class="grid g2">
        <label class="span2">Titolo *<input name="titolo" required value="<?= h($e['titolo']) ?>" placeholder="Es. Shooting, dentista, cena da mamma…"></label>
        <label>Tipo<select name="tipo" id="ev-tipo"><?= options(TIPI_EVENTO, $e['tipo']) ?></select></label>
        <label class="if-lavoro">Cliente<select name="cliente_id"><?= clienti_options($e['cliente_id'] ? (int)$e['cliente_id'] : null) ?></select></label>
        <label>Giorno<input type="date" name="data" required value="<?= h($e['data']) ?>"></label>
        <div class="grid g2 tight"><label>Dalle<input type="time" name="ora_inizio" value="<?= t($e['ora_inizio']) ?>"></label><label>Alle<input type="time" name="ora_fine" value="<?= t($e['ora_fine']) ?>"></label></div>
        <label>Si ripete<select name="ripeti" id="ev-ripeti"><?= options(RIPETI, $e['ripeti']) ?></select></label>
        <label class="if-ripeti">Fino al <span class="hint">vuoto = sempre</span><input type="date" name="ripeti_fino" value="<?= h($e['ripeti_fino']) ?>"></label>
        <label class="span2">Dove / link<input name="luogo" value="<?= h($e['luogo']) ?>" placeholder="Indirizzo o link della videochiamata"></label>
        <label class="span2">Note<textarea name="note" rows="3"><?= h($e['note']) ?></textarea></label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary">Salva</button>
        <a class="btn btn-ghost" href="<?= url('calendario') ?>">Annulla</a>
        <?php if ($eid): ?><button class="btn btn-danger push-right" name="azione" value="evento_elimina" formnovalidate data-conferma="<?= $e['ripeti'] !== 'no' ? 'Questo appuntamento si ripete: verranno eliminate tutte le date. Continuare?' : 'Eliminare questo appuntamento?' ?>"><?= icon('trash') ?> Elimina<?= $e['ripeti'] !== 'no' ? ' (tutte le date)' : '' ?></button><?php endif; ?>
      </div>
    </form>
    <?php layout_end();
    return;
}

// ---------- Importa da Excel / testo ----------
if (get('importa')) {
    layout_start('Importa appuntamenti', 'calendario');
    page_head('Importa appuntamenti', '', 'Incolla le date da Excel o da un documento: una riga per appuntamento, con la data e gli orari.');
    ?>
    <form method="post" action="<?= url('azioni') ?>" class="card form narrow">
      <?= csrf() ?><input type="hidden" name="azione" value="importa_testo">
      <div class="grid g2">
        <label class="span2">Titolo di tutti gli appuntamenti *<input name="titolo" required placeholder="Es. Corso Social + AI"></label>
        <label>Tipo<select name="tipo" id="ev-tipo"><?= options(TIPI_EVENTO, 'appuntamento') ?></select></label>
        <label class="if-lavoro">Cliente<select name="cliente_id"><?= clienti_options(null) ?></select></label>
        <label class="span2">Dove<input name="luogo"></label>
        <label class="span2">Date e orari *<textarea name="righe" rows="12" required class="mono" placeholder="venerdì 9 ottobre 2026	09:00	13:00&#10;venerdì 9 ottobre 2026	14:00	18:00&#10;12/10/2026 09:00 13:00"></textarea></label>
      </div>
      <p class="small muted">Vanno bene "9 ottobre 2026", "09/10/2026" o "2026-10-09", e gli orari come 09:00 o 9.00. Le righe senza data (per esempio le intestazioni) vengono ignorate. Gli appuntamenti che ci sono già, con lo stesso titolo, giorno e ora, vengono saltati: non si crea nessun doppione e non si cancella niente.</p>
      <div class="form-actions"><button class="btn btn-primary">Aggiungi al calendario</button><a class="btn btn-ghost" href="<?= url('calendario') ?>">Annulla</a></div>
    </form>
    <?php layout_end();
    return;
}

// ---------- Parametri ----------
$v = pick(get('v', $_SESSION['cal_v'] ?? 'mese'), ['mese' => 1, 'settimana' => 1, 'anno' => 1, 'timeline' => 1], 'mese');
$_SESSION['cal_v'] = $v;
$vista = pick(get('vista', $_SESSION['cal_vista'] ?? 'tutto'), ['tutto' => 1, 'lavoro' => 1, 'personale' => 1], 'tutto');
$_SESSION['cal_vista'] = $vista;
$lavoro = $vista !== 'personale';
$oggi = date('Y-m-d');
// giorno di riferimento
$rif = date_or_null(get('d')) ?? (preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') . '-01' : (preg_match('/^\d{4}$/', get('y')) ? get('y') . '-' . date('m-d') : $oggi));
$R = new DateTime($rif);
$m = $R->format('Y-m');

if ($v === 'settimana') {
    $from = (clone $R)->modify('monday this week')->format('Y-m-d');
    $to = (new DateTime($from))->modify('+6 days')->format('Y-m-d');
    $prev = (new DateTime($from))->modify('-7 days')->format('Y-m-d');
    $next = (new DateTime($from))->modify('+7 days')->format('Y-m-d');
    $f = new DateTime($from); $t = new DateTime($to);
    $titolo = $f->format('n') === $t->format('n') ? $f->format('j') . '–' . $t->format('j') . ' ' . MESI[(int)$t->format('n')] . ' ' . $t->format('Y')
        : $f->format('j') . ' ' . substr(MESI[(int)$f->format('n')], 0, 3) . ' – ' . $t->format('j') . ' ' . substr(MESI[(int)$t->format('n')], 0, 3) . ' ' . $t->format('Y');
} elseif ($v === 'anno') {
    $Y = (int)$R->format('Y');
    $from = "$Y-01-01"; $to = "$Y-12-31";
    $prev = ($Y - 1) . '-01-01'; $next = ($Y + 1) . '-01-01';
    $titolo = (string)$Y;
} elseif ($v === 'timeline') {
    $from = $rif; $to = (new DateTime($from))->modify('+59 days')->format('Y-m-d');
    $prev = (new DateTime($from))->modify('-60 days')->format('Y-m-d');
    $next = (new DateTime($from))->modify('+60 days')->format('Y-m-d');
    $titolo = 'Dal ' . d($from, true);
} else {
    $first = new DateTime($m . '-01');
    $from = (clone $first)->modify('monday this week')->format('Y-m-d');
    $to = (clone $first)->modify('last day of this month')->modify('sunday this week')->format('Y-m-d');
    $prev = (clone $first)->modify('-1 month')->format('Y-m-d');
    $next = (clone $first)->modify('+1 month')->format('Y-m-d');
    $titolo = ucfirst(MESI[(int)$first->format('n')]) . ' ' . $first->format('Y');
}

// ---------- Raccolta di tutto quello che c'è nel periodo ----------
$items = []; // data => [ ... ]
$add = function ($date, $kind, $text, $link, $time = '', $done = false, $ev = 0, $end = '') use (&$items) {
    $items[substr((string)$date, 0, 10)][] = compact('kind', 'text', 'link', 'time', 'done', 'ev', 'end');
};
foreach (eventi_range($from, $to, $vista) as $e)
    $add($e['quando'], $e['tipo'] === 'personale' ? 'personale' : 'evento', $e['titolo'] . (cliente_nome($e) ? ' · ' . cliente_nome($e) : '') . ($e['ripeti'] !== 'no' ? ' ↻' : ''), url('calendario', ['evento' => $e['id']]), t($e['ora_inizio']), false, (int)$e['id'], t($e['ora_fine']));
$tw = $vista === 'personale' ? ' AND t.personale=1' : ($vista === 'lavoro' ? ' AND t.personale=0' : '');
foreach (all('SELECT t.*, ' . C_COLS . " FROM task t LEFT JOIN clienti c ON c.id=t.cliente_id WHERE t.scadenza BETWEEN ? AND ? $tw", [$from, $to]) as $t)
    $add($t['scadenza'], $t['personale'] ? 'personale' : 'task', $t['titolo'] . (cliente_nome($t) ? ' · ' . cliente_nome($t) : ''), url('task', ['apri' => $t['id']]), t($t['ora']), $t['stato'] === 'fatto');
if ($lavoro):
foreach (all('SELECT ' . FATT_COLS . ', ' . C_COLS . ' FROM fatture f JOIN clienti c ON c.id=f.cliente_id WHERE f.annullata=0 AND f.scadenza BETWEEN ? AND ?', [$from, $to]) as $f)
    $add($f['scadenza'], 'soldi', 'Scade fattura ' . $f['numero'] . ' · ' . cliente_nome($f) . ' ' . money($f['totale'], false), url('fattura', ['id' => $f['id']]), '', $f['totale'] - $f['pagato'] <= 0.009);
foreach (all('SELECT p.*, ' . C_COLS . ' FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE p.scadenza BETWEEN ? AND ?', [$from, $to]) as $p)
    $add($p['scadenza'], 'progetto', 'Consegna: ' . $p['titolo'], url('progetto', ['id' => $p['id']]), '', $p['stato'] === 'completato');
foreach (all("SELECT p.*, " . C_COLS . " FROM preventivi p JOIN clienti c ON c.id=p.cliente_id WHERE p.stato='inviato'") as $p) {
    $sc = date('Y-m-d', strtotime($p['data'] . ' +' . (int)$p['validita_giorni'] . ' days'));
    if ($sc >= $from && $sc <= $to) $add($sc, 'commerciale', 'Scade preventivo ' . $p['numero'] . ' · ' . cliente_nome($p), url('preventivo', ['id' => $p['id']]));
}
foreach (all("SELECT k.*, " . C_COLS . " FROM contratti k JOIN clienti c ON c.id=k.cliente_id WHERE k.stato='attivo'") as $k) {
    $fine = contr_scadenza($k);
    if ($fine && $fine >= $from && $fine <= $to) $add($fine, 'soldi', ($k['rinnovo_automatico'] ? 'Rinnovo: ' : 'Scade gestione: ') . $k['titolo'] . ' · ' . cliente_nome($k), url('contratto', ['id' => $k['id']]));
    $nf = contr_prossima_fattura($k);
    if ($nf && $nf >= $from && $nf <= $to) $add($nf, 'soldi', 'Fatturare: ' . $k['titolo'] . ' · ' . cliente_nome($k), url('fattura', ['nuova' => 1, 'contratto_id' => $k['id']]));
}
endif;
foreach ($items as &$list) usort($list, fn($a, $b) => [$a['time'] === '', $a['time']] <=> [$b['time'] === '', $b['time']]);
unset($list);

$ical = setting('ical_attivo') === '1' && setting('ical_token') ? 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/ical.php?' . (spazio() !== '' ? 's=' . spazio() . '&' : '') . 'k=' . setting('ical_token') : '';
$pacchetti = pacchetti_da_importare();
$link = fn(array $x) => url('calendario', $x + ['v' => $v]);
// una voce del calendario (riusata in tutte le viste)
$voce = function (array $it, string $extra = '') {
    return '<a class="cal-i k-' . $it['kind'] . ($it['done'] ? ' done' : '') . ($it['ev'] ? ' cal-ev' : ' cal-other') . ' ' . $extra . '" href="' . h($it['link']) . '" title="' . h(($it['time'] ? $it['time'] . ($it['end'] ? '–' . $it['end'] : '') . ' ' : '') . $it['text']) . '">'
        . ($it['ev'] ? sel_box($it['ev']) : '') . ($it['time'] ? '<b>' . h($it['time']) . '</b> ' : '') . h($it['text']) . '</a>';
};

layout_start('Calendario', 'calendario');
page_head($titolo,
    '<div class="seg"><a class="btn" href="' . $link(['d' => $prev]) . '" aria-label="Precedente">‹</a><a class="btn" href="' . $link(['d' => $oggi]) . '">Oggi</a><a class="btn" href="' . $link(['d' => $next]) . '" aria-label="Successivo">›</a></div>
     <a class="btn" href="' . url('calendario', ['nuovo' => 1, 'personale' => 1]) . '">' . icon('plus') . ' Personale</a>
     <a class="btn btn-primary" href="' . url('calendario', ['nuovo' => 1]) . '">' . icon('plus') . ' Lavoro</a>
     <details class="menu"><summary class="btn" aria-label="Altro">⋯</summary><div class="menu-list">
       <a href="' . url('calendario', ['importa' => 1]) . '">Importa date da Excel</a>
     </div></details>
     ' . sel_toggle('appuntamenti'));
?>
<?php foreach ($pacchetti as $nome => $p): $dd = array_column($p['righe'], 0); ?>
<form method="post" action="<?= url('azioni') ?>" class="card callout import-box">
  <?= csrf() ?><input type="hidden" name="azione" value="importa_pacchetto"><input type="hidden" name="file" value="<?= h($nome) ?>">
  <div><b><?= h($p['titolo']) ?></b>: ci sono <?= count($p['righe']) ?> appuntamenti pronti da aggiungere, dal <?= d(min($dd)) ?> al <?= d(max($dd)) ?>.
    <div class="small muted">Vengono solo aggiunti: quello che hai già in calendario non viene toccato, e le date già presenti vengono saltate.</div></div>
  <div class="row"><button class="btn btn-primary">Aggiungi al calendario</button><button class="link-btn" name="ignora" value="1">Non ora, nascondi</button></div>
</form>
<?php endforeach; ?>

<div class="toolbar">
  <div class="tabs">
    <?php foreach (['mese' => 'Mese', 'settimana' => 'Settimana', 'anno' => 'Anno', 'timeline' => 'Timeline'] as $k => $l): ?><a class="<?= $v === $k ? 'on' : '' ?>" href="<?= url('calendario', ['v' => $k, 'd' => $k === 'timeline' && $rif < $oggi && $v !== 'timeline' ? $oggi : $rif]) ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
  <div class="tabs tabs-sm">
    <?php foreach (['tutto' => 'Tutto', 'lavoro' => 'Lavoro', 'personale' => '★ Personale'] as $k => $l): ?><a class="<?= $vista === $k ? 'on' : '' ?>" href="<?= $link(['d' => $rif, 'vista' => $k]) ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
</div>
<div class="legend small" style="margin:-4px 0 12px">
  <span class="k-personale">Personale</span><span class="k-evento">Appuntamenti</span><span class="k-task">Task</span><span class="k-progetto">Consegne</span><span class="k-commerciale">Commerciale</span><span class="k-soldi">Fatture e gestioni</span>
</div>

<div data-bulk>
<p class="sel-hint small">Tocca gli appuntamenti da eliminare. Se uno si ripete (↻), si eliminano tutte le sue ripetizioni.</p>

<?php if ($v === 'mese'): ?>
<div class="cal">
  <?php foreach (['Lun','Mar','Mer','Gio','Ven','Sab','Dom'] as $g): ?><div class="cal-h"><?= $g ?></div><?php endforeach; ?>
  <?php $d = new DateTime($from); $end = new DateTime($to);
  while ($d <= $end): $ds = $d->format('Y-m-d'); $out = $d->format('Y-m') !== $m; $list = $items[$ds] ?? []; ?>
    <div class="cal-d <?= $out ? 'out' : '' ?> <?= $ds === $oggi ? 'today' : '' ?> <?= $list ? 'has' : 'none' ?>">
      <a class="cal-n" href="<?= url('calendario', ['nuovo' => 1, 'data' => $ds] + ($vista === 'personale' ? ['personale' => 1] : [])) ?>" title="Aggiungi appuntamento"><span class="cal-wd"><?= GIORNI[(int)$d->format('w')] ?></span> <?= $d->format('j') ?></a>
      <?php foreach ($list as $it) echo $voce($it); ?>
    </div>
  <?php $d->modify('+1 day'); endwhile; ?>
</div>

<?php elseif ($v === 'settimana'):
  // fasce orarie: dalle 8 alle 20, allargate se serve
  $h0 = 8; $h1 = 20;
  foreach ($items as $list) foreach ($list as $it) if ($it['time']) {
      $h0 = min($h0, (int)substr($it['time'], 0, 2));
      $h1 = max($h1, (int)ceil(((int)substr($it['end'] ?: $it['time'], 0, 2) * 60 + (int)substr($it['end'] ?: $it['time'], 3, 2) + ($it['end'] ? 0 : 60)) / 60));
  }
  $H = 52; // pixel per ora
  $min = fn($hhmm) => (int)substr($hhmm, 0, 2) * 60 + (int)substr($hhmm, 3, 2);
?>
<div class="week" style="--rows:<?= $h1 - $h0 ?>;--H:<?= $H ?>px">
  <div class="wk-corner"></div>
  <?php for ($i = 0; $i < 7; $i++): $D = (new DateTime($from))->modify("+$i days"); $ds = $D->format('Y-m-d'); ?>
    <a class="wk-head <?= $ds === $oggi ? 'today' : '' ?>" href="<?= url('calendario', ['nuovo' => 1, 'data' => $ds] + ($vista === 'personale' ? ['personale' => 1] : [])) ?>" title="Aggiungi appuntamento"><span><?= ucfirst(GIORNI[(int)$D->format('w')]) ?></span><b><?= $D->format('j') ?></b></a>
  <?php endfor; ?>
  <div class="wk-allday-label">Tutto il giorno</div>
  <?php for ($i = 0; $i < 7; $i++): $ds = (new DateTime($from))->modify("+$i days")->format('Y-m-d'); ?>
    <div class="wk-allday"><?php foreach ($items[$ds] ?? [] as $it) if (!$it['time']) echo $voce($it); ?></div>
  <?php endfor; ?>
  <div class="wk-hours"><?php for ($h = $h0; $h < $h1; $h++): ?><div><?= sprintf('%02d:00', $h) ?></div><?php endfor; ?></div>
  <?php for ($i = 0; $i < 7; $i++): $ds = (new DateTime($from))->modify("+$i days")->format('Y-m-d');
    // colonne affiancate per gli appuntamenti che si sovrappongono
    $timed = array_values(array_filter($items[$ds] ?? [], fn($x) => $x['time']));
    $lanes = []; $pos = [];
    foreach ($timed as $k => $it) {
        $s = $min($it['time']); $e = $it['end'] ? max($min($it['end']), $s + 30) : $s + 60;
        $l = 0; while (isset($lanes[$l]) && $lanes[$l] > $s) $l++;
        $lanes[$l] = $e; $pos[$k] = [$s, $e, $l];
    }
    $nl = max(1, count($lanes)); ?>
    <div class="wk-col <?= $ds === $oggi ? 'today' : '' ?>">
      <?php foreach ($timed as $k => $it): [$s, $e, $l] = $pos[$k]; ?>
        <?= $voce($it, 'wk-ev" style="top:' . (($s - $h0 * 60) / 60 * $H) . 'px;height:' . max(22, ($e - $s) / 60 * $H - 2) . 'px;left:calc(' . ($l * 100 / $nl) . '% + 2px);width:calc(' . (100 / $nl) . '% - 4px)') ?>
      <?php endforeach; ?>
      <?php if ($ds === $oggi): $nowm = (int)date('G') * 60 + (int)date('i'); if ($nowm >= $h0 * 60 && $nowm <= $h1 * 60): ?><i class="wk-now" style="top:<?= ($nowm - $h0 * 60) / 60 * $H ?>px"></i><?php endif; endif; ?>
    </div>
  <?php endfor; ?>
</div>

<?php elseif ($v === 'anno'): ?>
<div class="year">
  <?php for ($mm = 1; $mm <= 12; $mm++):
    $F = new DateTime(sprintf('%04d-%02d-01', $Y, $mm));
    $start = (clone $F)->modify('monday this week'); $tot = 0;
    for ($x = clone $F; $x->format('n') == $mm; $x->modify('+1 day')) $tot += count($items[$x->format('Y-m-d')] ?? []); ?>
  <section class="ym">
    <a class="ym-title" href="<?= url('calendario', ['v' => 'mese', 'd' => $F->format('Y-m-d')]) ?>"><?= ucfirst(MESI[$mm]) ?> <em><?= $tot ?: '' ?></em></a>
    <div class="ym-grid">
      <?php foreach (['L','M','M','G','V','S','D'] as $g): ?><span class="ym-h"><?= $g ?></span><?php endforeach; ?>
      <?php for ($x = clone $start, $i = 0; $i < 42; $i++, $x->modify('+1 day')):
        if ($i >= 35 && $x->format('n') != $mm) break;
        $ds = $x->format('Y-m-d'); $in = $x->format('n') == $mm; $list = $in ? ($items[$ds] ?? []) : [];
        $kinds = array_values(array_unique(array_column($list, 'kind'))); ?>
        <a class="ym-d <?= $in ? '' : 'out' ?> <?= $ds === $oggi ? 'today' : '' ?> <?= $list ? 'has' : '' ?>" href="<?= url('calendario', ['v' => 'settimana', 'd' => $ds]) ?>" <?= $list ? 'title="' . h(count($list) . ' · ' . implode(' · ', array_slice(array_column($list, 'text'), 0, 4))) . '"' : '' ?>>
          <?= $in ? $x->format('j') : '' ?>
          <?php if ($list): ?><span class="ym-dots"><?php foreach (array_slice($kinds, 0, 3) as $k): ?><i class="k-<?= $k ?>"></i><?php endforeach; ?></span><?php endif; ?>
        </a>
      <?php endfor; ?>
    </div>
  </section>
  <?php endfor; ?>
</div>
<p class="muted small">Tocca un giorno per aprire la sua settimana, o il nome del mese per vederlo intero.</p>

<?php else: /* timeline */
  ksort($items); $mese_corr = ''; ?>
<div class="timeline-cal">
  <?php if (!$items): ?><?= empty_state('Niente in programma in questi 60 giorni.') ?><?php endif; ?>
  <?php foreach ($items as $ds => $list): $D = new DateTime($ds); $mk = $D->format('Y-m');
    if ($mk !== $mese_corr): $mese_corr = $mk; ?><h3 class="tlc-month"><?= ucfirst(MESI[(int)$D->format('n')]) ?> <?= $D->format('Y') ?></h3><?php endif; ?>
    <div class="tlc-day <?= $ds === $oggi ? 'today' : '' ?> <?= $ds < $oggi ? 'past' : '' ?>">
      <div class="tlc-date"><b><?= $D->format('j') ?></b><span><?= GIORNI[(int)$D->format('w')] ?></span></div>
      <div class="tlc-items">
        <?php foreach ($list as $it): ?>
          <div class="tlc-row"><span class="tlc-time"><?= $it['time'] ? h($it['time']) . ($it['end'] ? '–' . h($it['end']) : '') : 'giornata' ?></span><?= $voce($it) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="row" style="justify-content:space-between;margin-top:14px">
    <a class="btn btn-sm" href="<?= $link(['d' => $prev]) ?>">‹ 60 giorni prima</a>
    <a class="btn btn-sm" href="<?= $link(['d' => $next]) ?>">60 giorni dopo ›</a>
  </div>
</div>
<?php endif; ?>

<?= bulk_bar('eventi', [
  'elimina' => ['Elimina', 'Eliminare gli appuntamenti selezionati ({n})? Quelli che si ripetono vengono eliminati in tutte le date. Non si può annullare.', 'danger'],
]) ?>
</div>
<?php if ($ical): ?>
<details class="card small" style="margin-top:16px"><summary><b>Il calendario sul telefono è collegato</b></summary>
  <p>Indirizzo privato da aggiungere come "calendario da URL" (Google Calendar: Altri calendari → Da URL; iPhone: Impostazioni → Calendario → Account → Aggiungi calendario sottoscritto). Non condividerlo: chi lo ha vede i tuoi impegni. Puoi disattivarlo o cambiarlo da <a href="<?= url('impostazioni') ?>#sicurezza">Impostazioni</a>.</p>
  <input readonly value="<?= h($ical) ?>" onclick="this.select()" class="mono">
</details>
<?php else: ?>
<p class="muted small" style="margin-top:16px">Vuoi vedere queste scadenze nel calendario del telefono? Si attiva da <a href="<?= url('impostazioni') ?>#sicurezza">Impostazioni → Sicurezza e privacy</a>.</p>
<?php endif;
layout_end();
