<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$oggi = date('Y-m-d');
$u = user();
$ora = (int)date('G');
$saluto = $ora < 13 ? 'Buongiorno' : ($ora < 18 ? 'Buon pomeriggio' : 'Buonasera');
$nome = explode(' ', $u['nome'] ?? '')[0];

// Numeri
$fatt_anno = (float)val('SELECT COALESCE(SUM(imponibile),0) FROM fatture WHERE annullata=0 AND YEAR(data)=YEAR(CURDATE())');
$inc_mese = (float)val('SELECT COALESCE(SUM(pg.importo),0) FROM pagamenti pg JOIN fatture f ON f.id=pg.fattura_id WHERE f.annullata=0 AND DATE_FORMAT(pg.data,"%Y-%m")=DATE_FORMAT(CURDATE(),"%Y-%m")');
$aperte = all('SELECT x.*, ' . C_COLS . ' FROM (SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.annullata=0) x JOIN clienti c ON c.id=x.cliente_id WHERE x.totale - x.pagato > 0.009 ORDER BY x.scadenza IS NULL, x.scadenza');
$da_incassare = 0; $scadute = [];
foreach ($aperte as $f) { $da_incassare += $f['totale'] - $f['pagato']; if ($f['scadenza'] && $f['scadenza'] < $oggi) $scadute[] = $f; }
$prev_inviati = all("SELECT p.id, (SELECT COALESCE(SUM(quantita*prezzo),0) FROM preventivo_righe WHERE preventivo_id=p.id) - p.sconto imp FROM preventivi p WHERE p.stato='inviato'");
$in_attesa = array_sum(array_column($prev_inviati, 'imp'));
$mrr = 0;
$contratti = all("SELECT k.*, " . C_COLS . " FROM contratti k JOIN clienti c ON c.id=k.cliente_id WHERE k.stato='attivo'");
$da_fatturare = []; $in_scadenza = [];
foreach ($contratti as $k) {
    if (PERIODO_MESI[$k['periodicita']] > 0) $mrr += $k['importo'] / PERIODO_MESI[$k['periodicita']];
    $nf = contr_prossima_fattura($k);
    if ($nf && days_until($nf) <= 5) $da_fatturare[] = $k + ['_next' => $nf];
    $fine = contr_scadenza($k);
    if ($fine && days_until($fine) <= (int)$k['preavviso_giorni']) $in_scadenza[] = $k + ['_fine' => $fine];
}

// Oggi
$task_oggi = all("SELECT t.*, p.titolo AS progetto, " . C_COLS . " FROM task t LEFT JOIN progetti p ON p.id=t.progetto_id LEFT JOIN clienti c ON c.id=t.cliente_id
  WHERE t.stato<>'fatto' AND t.scadenza <= CURDATE() ORDER BY t.scadenza, t.ora IS NULL, t.ora, FIELD(t.priorita,'alta','media','bassa')");
$fatti_oggi = (int)val("SELECT COUNT(*) FROM task WHERE stato='fatto' AND DATE(completato_il)=CURDATE()");
$prossimi_task = all("SELECT t.*, p.titolo AS progetto, " . C_COLS . " FROM task t LEFT JOIN progetti p ON p.id=t.progetto_id LEFT JOIN clienti c ON c.id=t.cliente_id
  WHERE t.stato<>'fatto' AND t.scadenza BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY ORDER BY t.scadenza, t.ora LIMIT 8");
$eventi = eventi_range($oggi, date('Y-m-d', strtotime('+7 days')));
$prev_attesa = all("SELECT p.*, " . C_COLS . ", DATEDIFF(CURDATE(), p.data) giorni FROM preventivi p JOIN clienti c ON c.id=p.cliente_id WHERE p.stato='inviato' AND p.data <= CURDATE() - INTERVAL 5 DAY ORDER BY p.data");
$consegne = all("SELECT p.*, " . C_COLS . " FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE p.stato IN ('da_iniziare','in_corso','in_pausa') AND p.scadenza <= CURDATE() + INTERVAL 10 DAY ORDER BY p.scadenza");

layout_start('Oggi', 'dashboard');
page_head("$saluto" . ($nome ? ", $nome" : '') . '.', '', ucfirst(GIORNI_LUNGHI[(int)date('w')]) . ' ' . d($oggi, true));
?>
<div class="stats stats-4">
  <a class="stat" href="<?= url('fatture') ?>"><span>Fatturato <?= date('Y') ?></span><b><?= money($fatt_anno, false) ?></b><small>incassati questo mese <?= money($inc_mese, false) ?></small></a>
  <a class="stat <?= $scadute ? 'stat-alert' : '' ?>" href="<?= url('fatture', ['f' => 'aperte']) ?>"><span>Da incassare</span><b><?= money($da_incassare, false) ?></b><small><?= $scadute ? (count($scadute) === 1 ? '1 fattura scaduta' : count($scadute) . ' fatture scadute') : 'nessuna scaduta' ?></small></a>
  <a class="stat" href="<?= url('contratti') ?>"><span>Canoni mensili</span><b><?= money($mrr, false) ?></b><small><?= count($contratti) ?> gestioni attive</small></a>
  <a class="stat" href="<?= url('preventivi', ['stato' => 'inviato']) ?>"><span>Preventivi in attesa</span><b><?= money($in_attesa, false) ?></b><small><?= pl(count($prev_inviati), 'preventivo inviato', 'preventivi inviati') ?></small></a>
</div>

<div class="cols">
  <div class="col-main">
    <section class="card">
      <div class="card-head"><h2>Da fare oggi</h2><span class="muted small"><?= $fatti_oggi ? "$fatti_oggi già fatti oggi" : '' ?></span></div>
      <?php $task_list = $task_oggi; $show_client = true; ?>
      <form method="post" action="<?= url('azioni') ?>" class="quick-add">
        <?= csrf() ?><input type="hidden" name="azione" value="task_rapido"><input type="hidden" name="scadenza" value="<?= $oggi ?>">
        <input name="titolo" placeholder="Aggiungi qualcosa per oggi…" required>
        <select name="cliente_id" class="hide-sm"><?= task_chi_options(null, false, 'Lavoro') ?></select>
        <button class="btn btn-sm btn-primary"><?= icon('plus') ?></button>
      </form>
      <?php $quick = null; require APP_ROOT . '/pages/_task_list.php'; ?>
      <?php if ($prossimi_task): ?>
        <h3>Nei prossimi giorni</h3>
        <?php foreach ($prossimi_task as $t): ?>
          <a class="mini-item" href="<?= url('task', ['apri' => $t['id']]) ?>"><span class="due"><?= d_short($t['scadenza']) ?></span><span><?= h($t['titolo']) ?> <span class="muted small"><?= h(cliente_nome($t)) ?></span></span></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Agenda della settimana</h2><a class="small" href="<?= url('calendario') ?>">Calendario →</a></div>
      <?php if (!$eventi && !$consegne): ?><p class="muted small">Nessun appuntamento nei prossimi 7 giorni.</p><?php endif; ?>
      <?php foreach ($eventi as $e): ?>
        <a class="agenda-row" href="<?= url('calendario', ['evento' => $e['id']]) ?>">
          <span class="agenda-date <?= $e['quando'] === $oggi ? 'today' : '' ?>"><b><?= date('j', strtotime($e['quando'])) ?></b><?= GIORNI[(int)date('w', strtotime($e['quando']))] ?></span>
          <span><b><?= t($e['ora_inizio']) ?></b> <?= h($e['titolo']) ?><?= $e['tipo'] === 'personale' ? ' <span class="badge b-personale">★</span>' : '' ?><br><span class="muted small"><?= h(implode(' · ', array_filter([cliente_nome($e), $e['luogo']]))) ?></span></span>
        </a>
      <?php endforeach; ?>
      <?php foreach ($consegne as $p): ?>
        <a class="agenda-row" href="<?= url('progetto', ['id' => $p['id']]) ?>">
          <span class="agenda-date <?= due_class($p['scadenza']) ?>"><b><?= date('j', strtotime($p['scadenza'])) ?></b><?= GIORNI[(int)date('w', strtotime($p['scadenza']))] ?></span>
          <span>Consegna <b><?= h($p['titolo']) ?></b><br><span class="muted small"><?= h(cliente_nome($p)) ?> · <?= rel_days($p['scadenza']) ?></span></span>
        </a>
      <?php endforeach; ?>
    </section>
  </div>

  <div class="col-side">
    <?php if ($scadute || $da_fatturare): ?>
    <section class="card callout">
      <h2><?= icon('euro') ?> Soldi</h2>
      <?php foreach ($scadute as $f): ?>
        <a class="mini-item" href="<?= url('fattura', ['id' => $f['id']]) ?>"><span><b><?= h(cliente_nome($f)) ?></b> deve <?= money($f['totale'] - $f['pagato']) ?><br><span class="due is-late small">fattura <?= h($f['numero']) ?> scaduta <?= rel_days($f['scadenza']) ?></span></span></a>
      <?php endforeach; ?>
      <?php foreach ($da_fatturare as $k): ?>
        <a class="mini-item" href="<?= url('fattura', ['nuova' => 1, 'contratto_id' => $k['id']]) ?>"><span>Fattura da fare: <b><?= h(cliente_nome($k)) ?></b> · <?= h($k['titolo']) ?> <?= money($k['importo'], false) ?><br><span class="due <?= due_class($k['_next']) ?> small"><?= d($k['_next']) ?></span></span></a>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($prev_attesa): ?>
    <section class="card">
      <h2>Preventivi senza risposta</h2>
      <?php foreach ($prev_attesa as $p): ?>
        <a class="mini-item" href="<?= url('preventivo', ['id' => $p['id']]) ?>"><span>Preventivo a <b><?= h(cliente_nome($p)) ?></b> senza risposta<br><span class="muted small">inviato <?= $p['giorni'] ?> giorni fa · <?= h($p['oggetto']) ?></span></span></a>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if ($in_scadenza): ?>
    <section class="card">
      <h2>Gestioni in scadenza</h2>
      <?php foreach ($in_scadenza as $k): ?>
        <a class="mini-item" href="<?= url('contratto', ['id' => $k['id']]) ?>"><span><b><?= h(cliente_nome($k)) ?></b> · <?= h($k['titolo']) ?><br><span class="due <?= days_until($k['_fine']) < 0 ? 'is-late' : 'is-soon' ?> small"><?= $k['rinnovo_automatico'] ? 'si rinnova' : 'scade' ?> <?= rel_days($k['_fine']) ?> (<?= d($k['_fine']) ?>)</span></span></a>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php if (!$scadute && !$da_fatturare && !$prev_attesa && !$in_scadenza): ?>
    <section class="card"><h2>Tutto in ordine</h2><p class="muted small">Nessuna fattura scaduta, nessun cliente da richiamare, nessuna gestione in scadenza.</p></section>
    <?php endif; ?>
  </div>
</div>
<?php layout_end();
