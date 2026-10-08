<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$p = $id ? one('SELECT * FROM preventivi WHERE id=?', [$id]) : null;
if ($id && !$p) redirect(url('preventivi'));

function salva_righe(int $pid): void {
    q('DELETE FROM preventivo_righe WHERE preventivo_id=?', [$pid]);
    $i = 0;
    foreach ((array)($_POST['righe'] ?? []) as $r) {
        $desc = trim((string)($r['descrizione'] ?? ''));
        if ($desc === '') continue;
        insert('preventivo_righe', ['preventivo_id' => $pid, 'descrizione' => $desc, 'quantita' => dec($r['quantita'] ?? 1) ?: 1, 'prezzo' => dec($r['prezzo'] ?? 0), 'ordine' => $i++]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $cid = id_or_null(post('cliente_id'));
        if (!$cid) { flash('Scegli il cliente.', 'err'); back(); }
        $data = [
            'cliente_id' => $cid, 'trattativa_id' => id_or_null(post('trattativa_id')),
            'numero' => post('numero') ?: next_numero_preventivo(), 'data' => date_or_null(post('data')) ?? date('Y-m-d'),
            'validita_giorni' => max(1, (int)post('validita_giorni', 30)), 'oggetto' => post('oggetto') ?: 'Preventivo',
            'iva_percentuale' => dec(post('iva_percentuale')), 'sconto' => dec(post('sconto')),
            'introduzione' => nul(post('introduzione')), 'condizioni' => nul(post('condizioni')), 'note_interne' => nul(post('note_interne')),
        ];
        db()->beginTransaction();
        if ($id) update('preventivi', $id, $data);
        else {
            $id = insert('preventivi', $data + ['stato' => 'bozza']);
            log_act($cid, 'Preventivo ' . $data['numero'] . ' creato: ' . $data['oggetto'], url('preventivo', ['id' => $id]));
        }
        salva_righe($id);
        db()->commit();
        flash('Preventivo salvato.');
        redirect(post('poi') === 'stampa' ? url('preventivo_stampa', ['id' => $id]) : url('preventivo', ['id' => $id]));
    }
    if ($p && $az === 'stato') {
        $nuovo = pick(post('stato'), STATI_PREV, $p['stato']);
        update('preventivi', $id, ['stato' => $nuovo]);
        $tot = prev_totali($id);
        log_act((int)$p['cliente_id'], 'Preventivo ' . $p['numero'] . ' (' . money($tot['imponibile'], false) . '): ' . mb_strtolower(STATI_PREV[$nuovo]), url('preventivo', ['id' => $id]));
        if ($p['trattativa_id']) {
            $map = ['inviato' => 'preventivo', 'accettato' => 'vinta', 'rifiutato' => 'persa'];
            if (isset($map[$nuovo])) update('trattative', (int)$p['trattativa_id'], ['fase' => $map[$nuovo], 'probabilita' => FASI_PROB[$map[$nuovo]], 'valore' => $tot['imponibile']]);
        }
        if ($nuovo === 'accettato') {
            q("UPDATE clienti SET stato='cliente' WHERE id=? AND stato='potenziale'", [$p['cliente_id']]);
            flash('Preventivo accettato. Ora puoi creare il progetto o la fattura da qui.');
        } else flash('Stato aggiornato: ' . STATI_PREV[$nuovo] . '.');
        redirect(url('preventivo', ['id' => $id]));
    }
    if ($p && $az === 'duplica') {
        $new = $p; unset($new['id'], $new['created_at'], $new['updated_at']);
        $new['numero'] = next_numero_preventivo(); $new['data'] = date('Y-m-d'); $new['stato'] = 'bozza';
        $nid = insert('preventivi', $new);
        foreach (all('SELECT descrizione, quantita, prezzo, ordine FROM preventivo_righe WHERE preventivo_id=?', [$id]) as $r) insert('preventivo_righe', $r + ['preventivo_id' => $nid]);
        flash('Preventivo duplicato come ' . $new['numero'] . '.');
        redirect(url('preventivo', ['id' => $nid]));
    }
    if ($p && $az === 'crea_progetto') {
        $righe = all('SELECT descrizione FROM preventivo_righe WHERE preventivo_id=? ORDER BY ordine', [$id]);
        $pid = insert('progetti', ['cliente_id' => $p['cliente_id'], 'preventivo_id' => $id, 'titolo' => $p['oggetto'], 'stato' => 'da_iniziare',
            'data_inizio' => date('Y-m-d'), 'budget' => prev_totali($id)['imponibile']]);
        foreach ($righe as $i => $r) {
            $titolo = strtok($r['descrizione'], "\n");
            insert('task', ['progetto_id' => $pid, 'cliente_id' => $p['cliente_id'], 'titolo' => mb_substr($titolo, 0, 250), 'ordine' => $i]);
        }
        log_act((int)$p['cliente_id'], 'Progetto avviato dal preventivo ' . $p['numero'] . ': ' . $p['oggetto'], url('progetto', ['id' => $pid]));
        flash('Progetto creato: ogni voce del preventivo è diventata un task.');
        redirect(url('progetto', ['id' => $pid]));
    }
    if ($p && $az === 'elimina') {
        delete_row('preventivi', $id);
        flash('Preventivo eliminato.');
        redirect(url('preventivi'));
    }
}

// ---------- Dati per il modulo ----------
if (!$p) {
    $cid = (int)get('cliente_id') ?: null;
    $tid = (int)get('trattativa_id') ?: null;
    $tr = $tid ? one('SELECT * FROM trattative WHERE id=?', [$tid]) : null;
    $p = ['cliente_id' => $cid, 'trattativa_id' => $tid, 'numero' => next_numero_preventivo(), 'data' => date('Y-m-d'), 'validita_giorni' => 30,
          'oggetto' => $tr['titolo'] ?? '', 'stato' => 'bozza', 'iva_percentuale' => setting('iva_default', '22'), 'sconto' => 0,
          'introduzione' => '', 'condizioni' => setting('condizioni_default'), 'note_interne' => ''];
    $righe = [['descrizione' => '', 'quantita' => 1, 'prezzo' => $tr['valore'] ?? 0]];
} else {
    $righe = all('SELECT * FROM preventivo_righe WHERE preventivo_id=? ORDER BY ordine', [$id]) ?: [['descrizione' => '', 'quantita' => 1, 'prezzo' => 0]];
}
$tratt_cliente = $p['cliente_id'] ? all("SELECT id, titolo FROM trattative WHERE cliente_id=? ORDER BY fase IN ('vinta','persa'), updated_at DESC", [$p['cliente_id']]) : [];
$listino = [];
foreach (preg_split('/\R/', setting('listino')) as $line) {
    if (!trim($line)) continue;
    $parts = array_map('trim', explode('|', $line));
    $listino[] = ['d' => $parts[0], 'p' => dec($parts[1] ?? 0)];
}
$progetti = $id ? all('SELECT id, titolo FROM progetti WHERE preventivo_id=?', [$id]) : [];
$fatture = $id ? all('SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.preventivo_id=?', [$id]) : [];

layout_start($id ? 'Preventivo ' . $p['numero'] : 'Nuovo preventivo', 'preventivi');
$actions = $id ? '<a class="btn" href="' . url('preventivo_stampa', ['id' => $id]) . '" target="_blank">' . icon('print') . ' Stampa / PDF</a>' : '';
page_head($id ? 'Preventivo ' . $p['numero'] : 'Nuovo preventivo', $actions, $id ? badge($p['stato'], STATI_PREV[$p['stato']]) . ' <span class="muted">' . h($p['oggetto']) . '</span>' : '');
?>
<div class="cols">
<form method="post" class="card form col-main" id="prev-form">
  <?= csrf() ?><input type="hidden" name="azione" value="salva">
  <div class="grid g4">
    <label class="span2">Cliente *<select name="cliente_id" required><?= clienti_options($p['cliente_id'] ? (int)$p['cliente_id'] : null, true, '— Scegli —') ?></select></label>
    <label>Numero<input name="numero" value="<?= h($p['numero']) ?>"></label>
    <label>Data<input type="date" name="data" value="<?= h($p['data']) ?>"></label>
    <label class="span2">Oggetto *<input name="oggetto" required value="<?= h($p['oggetto']) ?>" placeholder="Es. Strategia social e gestione Instagram"></label>
    <label>Validità (giorni)<input type="number" name="validita_giorni" min="1" value="<?= (int)$p['validita_giorni'] ?>"></label>
    <input type="hidden" name="trattativa_id" value="<?= h($p['trattativa_id']) ?>">
    <label class="span-all">Introduzione <span class="hint">facoltativa, compare prima delle voci</span><textarea name="introduzione" rows="2"><?= h($p['introduzione']) ?></textarea></label>
  </div>

  <h3>Voci</h3>
  <div class="lines" id="lines">
    <div class="line line-head"><span>Descrizione</span><span>Q.tà</span><span>Prezzo €</span><span class="num">Totale</span><span></span></div>
    <?php foreach ($righe as $i => $r): ?>
    <div class="line">
      <textarea name="righe[<?= $i ?>][descrizione]" rows="2" placeholder="Descrizione del servizio"><?= h($r['descrizione']) ?></textarea>
      <input name="righe[<?= $i ?>][quantita]" inputmode="decimal" value="<?= num_it($r['quantita']) ?>" class="q">
      <input name="righe[<?= $i ?>][prezzo]" inputmode="decimal" value="<?= num_it($r['prezzo']) ?>" class="pz">
      <span class="num line-tot"></span>
      <button type="button" class="icon-btn rm" title="Togli"><?= icon('trash') ?></button>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="row">
    <button type="button" class="btn btn-sm" id="add-line"><?= icon('plus') ?> Aggiungi voce</button>
    <?php if ($listino): ?>
    <select id="listino" class="btn-sm"><option value="">+ Dal listino…</option><?php foreach ($listino as $l): ?><option data-p="<?= $l['p'] ?>" value="<?= h($l['d']) ?>"><?= h($l['d']) ?> — <?= money($l['p'], false) ?></option><?php endforeach; ?></select>
    <?php else: ?><a class="muted small" href="<?= url('impostazioni') ?>#listino">Imposta un listino servizi</a><?php endif; ?>
  </div>

  <div class="totals">
    <div><span>Subtotale</span><b id="t-sub"></b></div>
    <div><span>Sconto €</span><input name="sconto" inputmode="decimal" value="<?= num_it($p['sconto']) ?>" id="sconto"></div>
    <div><span>Imponibile</span><b id="t-imp"></b></div>
    <div><span>IVA <input name="iva_percentuale" inputmode="decimal" value="<?= num_it($p['iva_percentuale']) ?>" id="iva" class="tiny">%</span><b id="t-iva"></b></div>
    <div class="grand"><span>Totale</span><b id="t-tot"></b></div>
  </div>

  <label>Condizioni <span class="hint">pagamento, tempi, cosa è escluso</span><textarea name="condizioni" rows="4"><?= h($p['condizioni']) ?></textarea></label>
  <label>Note interne <span class="hint">non compaiono sul preventivo</span><textarea name="note_interne" rows="2"><?= h($p['note_interne']) ?></textarea></label>
  <div class="form-actions">
    <button class="btn btn-primary">Salva</button>
    <button class="btn" name="poi" value="stampa">Salva e apri il PDF</button>
    <a class="btn btn-ghost" href="<?= url('preventivi') ?>">Annulla</a>
  </div>
</form>

<?php if ($id): $tot = prev_totali($id); ?>
<div class="col-side">
  <section class="card">
    <h2>Stato</h2>
    <form method="post" class="stack"><?= csrf() ?><input type="hidden" name="azione" value="stato">
      <?php if ($p['stato'] === 'bozza'): ?><button class="btn btn-primary btn-block" name="stato" value="inviato">Segna come inviato</button><?php endif; ?>
      <?php if (in_array($p['stato'], ['bozza', 'inviato'])): ?>
        <button class="btn btn-block btn-ok" name="stato" value="accettato">✓ Accettato</button>
        <button class="btn btn-block" name="stato" value="rifiutato">Rifiutato</button>
      <?php else: ?>
        <button class="btn btn-block btn-ghost" name="stato" value="inviato">Riporta a "Inviato"</button>
      <?php endif; ?>
    </form>
    <?php if ($p['stato'] === 'inviato'): $sc = date('Y-m-d', strtotime($p['data'] . ' +' . (int)$p['validita_giorni'] . ' days')); ?>
      <p class="small muted">Valido fino al <?= d($sc) ?> (<?= rel_days($sc) ?>).</p>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>E poi</h2>
    <form method="post" class="stack"><?= csrf() ?>
      <?php foreach ($progetti as $pr): ?><a class="mini-item" href="<?= url('progetto', ['id' => $pr['id']]) ?>"><?= icon('folder') ?><span>Progetto: <?= h($pr['titolo']) ?></span></a><?php endforeach; ?>
      <?php foreach ($fatture as $f): ?><a class="mini-item" href="<?= url('fattura', ['id' => $f['id']]) ?>"><?= icon('euro') ?><span>Fattura <?= h($f['numero']) ?> · <?= money($f['totale']) ?></span></a><?php endforeach; ?>
      <?php if (!$progetti): ?><button class="btn btn-block" name="azione" value="crea_progetto"><?= icon('folder') ?> Crea progetto con i task</button><?php endif; ?>
      <a class="btn btn-block" href="<?= url('fattura', ['nuova' => 1, 'preventivo_id' => $id]) ?>"><?= icon('euro') ?> Crea fattura</a>
      <a class="btn btn-block" href="<?= url('contratto', ['modifica' => 1, 'cliente_id' => $p['cliente_id'], 'titolo' => $p['oggetto'], 'importo' => $tot['imponibile']]) ?>"><?= icon('contract') ?> Crea gestione ricorrente</a>
      <button class="btn btn-block btn-ghost" name="azione" value="duplica"><?= icon('copy') ?> Duplica</button>
      <button class="link-btn danger" name="azione" value="elimina" data-conferma="Eliminare il preventivo?">Elimina preventivo</button>
    </form>
  </section>
</div>
<?php endif; ?>
</div>
<?php layout_end();
