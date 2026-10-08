<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$k = $id ? one('SELECT * FROM contratti WHERE id=?', [$id]) : null;
if ($id && !$k) redirect(url('contratti'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $cid = id_or_null(post('cliente_id'));
        if (!$cid) { flash('Scegli il cliente.', 'err'); back(); }
        $data = [
            'cliente_id' => $cid, 'titolo' => post('titolo') ?: 'Gestione', 'importo' => dec(post('importo')),
            'periodicita' => pick(post('periodicita'), PERIODICITA, 'mensile'),
            'data_inizio' => date_or_null(post('data_inizio')) ?? date('Y-m-d'), 'data_fine' => date_or_null(post('data_fine')),
            'rinnovo_automatico' => post('rinnovo_automatico') ? 1 : 0, 'preavviso_giorni' => max(0, (int)post('preavviso_giorni', 30)),
            'stato' => pick(post('stato'), STATI_CONTR, 'attivo'), 'note' => nul(post('note')),
        ];
        if ($id) {
            if ($k['stato'] !== $data['stato']) log_act($cid, 'Gestione "' . $data['titolo'] . '": ' . mb_strtolower(STATI_CONTR[$data['stato']]), url('contratto', ['id' => $id]));
            update('contratti', $id, $data);
        } else {
            $id = insert('contratti', $data);
            log_act($cid, 'Nuova gestione: ' . $data['titolo'] . ' (' . money($data['importo'], false) . ' ' . mb_strtolower(PERIODICITA[$data['periodicita']]) . ')', url('contratto', ['id' => $id]));
            q("UPDATE clienti SET stato='cliente' WHERE id=? AND stato='potenziale'", [$cid]);
        }
        $err = salva_allegato('contratto', $id, $_FILES['file'] ?? []);
        if ($err) flash($err, 'err');
        flash('Gestione salvata.');
        redirect(url('contratto', ['id' => $id]));
    }
    if ($k && $az === 'rinnova') {
        // nuovo periodo con la stessa durata
        $fine = $k['data_fine'] ?: date('Y-m-d');
        $nf = sposta_fine($fine, durata_mesi($k['data_inizio'], $fine));
        update('contratti', $id, ['data_fine' => $nf, 'stato' => 'attivo']);
        log_act((int)$k['cliente_id'], 'Gestione "' . $k['titolo'] . '" rinnovato fino al ' . d($nf), url('contratto', ['id' => $id]));
        flash('Rinnovato fino al ' . d($nf) . '.');
        redirect(url('contratto', ['id' => $id]));
    }
    if ($k && $az === 'elimina') {
        foreach (all("SELECT file FROM allegati WHERE entita='contratto' AND entita_id=?", [$id]) as $a) @unlink(upload_dir() . '/' . basename($a['file']));
        q("DELETE FROM allegati WHERE entita='contratto' AND entita_id=?", [$id]);
        delete_row('contratti', $id);
        flash('Gestione eliminata.');
        redirect(url('contratti'));
    }
}

if (!$k) $k = ['cliente_id' => (int)get('cliente_id') ?: null, 'titolo' => get('titolo'), 'importo' => get('importo') !== '' ? (float)get('importo') : '',
    'periodicita' => 'mensile', 'data_inizio' => date('Y-m-01', strtotime('+1 month')), 'data_fine' => '', 'rinnovo_automatico' => 0,
    'preavviso_giorni' => setting('preavviso_default', '30'), 'stato' => 'attivo', 'note' => ''];
$fatture = $id ? all('SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.contratto_id=? ORDER BY f.data DESC', [$id]) : [];

layout_start($id ? $k['titolo'] : 'Nuova gestione', 'contratti');
page_head($id ? $k['titolo'] : 'Nuova gestione', '', $id ? badge($k['stato'], STATI_CONTR[$k['stato']]) : '');
?>
<div class="cols">
<form method="post" class="card form col-main" enctype="multipart/form-data">
  <?= csrf() ?><input type="hidden" name="azione" value="salva">
  <div class="grid g3">
    <label class="span2">Servizio / gestione *<input name="titolo" required value="<?= h($k['titolo']) ?>" placeholder="Es. Gestione social mensile"></label>
    <label>Cliente *<select name="cliente_id" required><?= clienti_options($k['cliente_id'] ? (int)$k['cliente_id'] : null, true, '— Scegli —') ?></select></label>
    <label>Importo € <span class="hint">per ogni periodo, IVA esclusa</span><input name="importo" inputmode="decimal" value="<?= $k['importo'] === '' ? '' : num_it($k['importo']) ?>"></label>
    <label>Ogni quanto si fattura<select name="periodicita"><?= options(PERIODICITA, $k['periodicita']) ?></select></label>
    <label>Stato<select name="stato"><?= options(STATI_CONTR, $k['stato']) ?></select></label>
    <label>Inizio<input type="date" name="data_inizio" value="<?= h($k['data_inizio']) ?>" required></label>
    <label>Fine <span class="hint">vuoto = senza scadenza</span><input type="date" name="data_fine" value="<?= h($k['data_fine']) ?>"></label>
    <label>Avvisami prima di (giorni)<input type="number" name="preavviso_giorni" min="0" value="<?= (int)$k['preavviso_giorni'] ?>"></label>
  </div>
  <label class="check"><input type="checkbox" name="rinnovo_automatico" value="1" <?= $k['rinnovo_automatico'] ? 'checked' : '' ?>> Si rinnova automaticamente alla scadenza, salvo disdetta</label>
  <label>Note <span class="hint">clausole, cosa è incluso, come si disdice</span><textarea name="note" rows="4"><?= h($k['note']) ?></textarea></label>
  <label>Allega il contratto o l'accordo firmato<input type="file" name="file"></label>
  <div class="form-actions">
    <button class="btn btn-primary">Salva</button>
    <a class="btn btn-ghost" href="<?= url('contratti') ?>">Annulla</a>
    <?php if ($id): ?><button class="link-btn danger push-right" name="azione" value="elimina" formnovalidate data-conferma="Eliminare la gestione? Le fatture collegate restano.">Elimina</button><?php endif; ?>
  </div>
</form>
<?php if ($id): $fine = contr_scadenza($k); $next = contr_prossima_fattura($k); ?>
<div class="col-side">
  <section class="card">
    <h2>Scadenze</h2>
    <dl class="kv">
      <dt>Cliente</dt><dd><a href="<?= url('cliente', ['id' => $k['cliente_id']]) ?>"><?= h(val('SELECT COALESCE(NULLIF(nome_breve,\'\'), ragione_sociale) FROM clienti WHERE id=?', [$k['cliente_id']])) ?></a></dd>
      <?php if ($fine): ?><dt><?= $k['rinnovo_automatico'] ? 'Prossimo rinnovo' : 'Scade il' ?></dt><dd><span class="due <?= $k['stato'] === 'attivo' && days_until($fine) <= $k['preavviso_giorni'] ? (days_until($fine) < 0 ? 'is-late' : 'is-soon') : '' ?>"><?= d($fine) ?></span> <span class="muted small"><?= rel_days($fine) ?></span></dd>
      <dt>Disdetta entro</dt><dd><?= d(date('Y-m-d', strtotime($fine . ' -' . (int)$k['preavviso_giorni'] . ' days'))) ?></dd><?php endif; ?>
      <?php if ($next): ?><dt>Prossima fattura</dt><dd><span class="due <?= due_class($next) ?>"><?= d($next) ?></span></dd><?php endif; ?>
    </dl>
    <div class="stack">
      <?php if ($next): ?><a class="btn btn-primary btn-block" href="<?= url('fattura', ['nuova' => 1, 'contratto_id' => $id]) ?>"><?= icon('euro') ?> Registra la fattura</a><?php endif; ?>
      <?php if ($k['data_fine'] && !$k['rinnovo_automatico']): ?>
        <form method="post"><?= csrf() ?><input type="hidden" name="azione" value="rinnova"><button class="btn btn-block"><?= icon('contract') ?> Rinnova per un altro periodo</button></form>
      <?php endif; ?>
    </div>
  </section>
  <section class="card">
    <h2>Fatture della gestione</h2>
    <?php foreach ($fatture as $f): [$sk, $sl] = fatt_stato($f); ?>
      <a class="mini-item" href="<?= url('fattura', ['id' => $f['id']]) ?>"><span><?= d($f['data']) ?> · n. <?= h($f['numero']) ?> · <?= money($f['totale']) ?> <?= badge($sk, $sl) ?></span></a>
    <?php endforeach; ?>
    <?php if (!$fatture): ?><p class="muted small">Ancora nessuna.</p><?php endif; ?>
  </section>
  <section class="card"><h2>File</h2><?= allegati_box('contratto', $id) ?></section>
</div>
<?php endif; ?>
</div>
<?php layout_end();
