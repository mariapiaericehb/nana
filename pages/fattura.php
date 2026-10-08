<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$f = $id ? one('SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.id=?', [$id]) : null;
if ($id && !$f) redirect(url('fatture'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $cid = id_or_null(post('cliente_id'));
        if (!$cid) { flash('Scegli il cliente.', 'err'); back(); }
        $data = [
            'cliente_id' => $cid, 'preventivo_id' => id_or_null(post('preventivo_id')), 'contratto_id' => id_or_null(post('contratto_id')),
            'numero' => post('numero') ?: next_numero_fattura(), 'data' => date_or_null(post('data')) ?? date('Y-m-d'),
            'scadenza' => date_or_null(post('scadenza')), 'descrizione' => nul(post('descrizione')),
            'imponibile' => dec(post('imponibile')), 'iva_percentuale' => dec(post('iva_percentuale')),
            'ritenuta_percentuale' => dec(post('ritenuta_percentuale')), 'note' => nul(post('note')),
            'annullata' => post('annullata') ? 1 : 0,
        ];
        if ($id) update('fatture', $id, $data);
        else {
            $id = insert('fatture', $data);
            log_act($cid, 'Fattura ' . $data['numero'] . ' registrata: ' . money($data['imponibile']) . ' + IVA', url('fattura', ['id' => $id]));
            if (post('gia_pagata')) {
                $t = one('SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.id=?', [$id]);
                insert('pagamenti', ['fattura_id' => $id, 'data' => $data['data'], 'importo' => $t['totale'], 'metodo' => 'Bonifico']);
            }
        }
        $err = salva_allegato('fattura', $id, $_FILES['file'] ?? []);
        if ($err) flash($err, 'err');
        flash('Fattura salvata.');
        redirect(url('fattura', ['id' => $id]));
    }
    if ($f && $az === 'pagamento') {
        $imp = dec(post('importo'));
        if ($imp > 0) {
            insert('pagamenti', ['fattura_id' => $id, 'data' => date_or_null(post('data')) ?? date('Y-m-d'), 'importo' => $imp, 'metodo' => nul(post('metodo')), 'note' => nul(post('note'))]);
            $res = round($f['totale'] - $f['pagato'] - $imp, 2);
            log_act((int)$f['cliente_id'], 'Incassati ' . money($imp) . ' sulla fattura ' . $f['numero'] . ($res <= 0 ? ' (saldata)' : ' (restano ' . money($res) . ')'), url('fattura', ['id' => $id]));
            flash($res <= 0 ? 'Pagamento registrato: fattura saldata.' : 'Pagamento registrato. Restano ' . money($res) . '.');
        }
        redirect(url('fattura', ['id' => $id]));
    }
    if ($f && $az === 'elimina_pagamento') {
        q('DELETE FROM pagamenti WHERE id=? AND fattura_id=?', [(int)post('pid'), $id]);
        redirect(url('fattura', ['id' => $id]));
    }
    if ($f && $az === 'sollecito') {
        log_act((int)$f['cliente_id'], 'Sollecito di pagamento per la fattura ' . $f['numero'], url('fattura', ['id' => $id]), 'email');
        flash('Sollecito segnato nello storico del cliente.');
        redirect(url('fattura', ['id' => $id]));
    }
    if ($f && $az === 'elimina') {
        foreach (all("SELECT file FROM allegati WHERE entita='fattura' AND entita_id=?", [$id]) as $a) @unlink(upload_dir() . '/' . basename($a['file']));
        q("DELETE FROM allegati WHERE entita='fattura' AND entita_id=?", [$id]);
        delete_row('fatture', $id);
        flash('Fattura eliminata.');
        redirect(url('fatture'));
    }
}

// valori iniziali
if (!$f) {
    $gg = (int)setting('giorni_pagamento', '30');
    $f = ['cliente_id' => (int)get('cliente_id') ?: null, 'preventivo_id' => null, 'contratto_id' => null, 'numero' => next_numero_fattura(),
          'data' => date('Y-m-d'), 'scadenza' => date('Y-m-d', strtotime("+$gg days")), 'descrizione' => '', 'imponibile' => '',
          'iva_percentuale' => setting('iva_default', '22'), 'ritenuta_percentuale' => setting('ritenuta_default', '0'), 'note' => '', 'annullata' => 0];
    if ($pid = (int)get('preventivo_id')) {
        $pr = one('SELECT * FROM preventivi WHERE id=?', [$pid]);
        if ($pr) {
            $t = prev_totali($pid);
            $f = array_merge($f, ['cliente_id' => $pr['cliente_id'], 'preventivo_id' => $pid, 'descrizione' => $pr['oggetto'], 'imponibile' => $t['imponibile'], 'iva_percentuale' => $pr['iva_percentuale']]);
            $gia = (float)val('SELECT COALESCE(SUM(imponibile),0) FROM fatture WHERE preventivo_id=? AND annullata=0', [$pid]);
            if ($gia > 0) $f['imponibile'] = max(0, $t['imponibile'] - $gia);
        }
    }
    if ($kid = (int)get('contratto_id')) {
        $k = one('SELECT * FROM contratti WHERE id=?', [$kid]);
        if ($k) {
            $periodo = $k['periodicita'] === 'mensile' ? ' – ' . MESI[(int)date('n')] . ' ' . date('Y') : '';
            $f = array_merge($f, ['cliente_id' => $k['cliente_id'], 'contratto_id' => $kid, 'descrizione' => $k['titolo'] . $periodo, 'imponibile' => $k['importo']]);
        }
    }
}
$pagamenti = $id ? all('SELECT * FROM pagamenti WHERE fattura_id=? ORDER BY data', [$id]) : [];
$contratti = $f['cliente_id'] ? all('SELECT id, titolo FROM contratti WHERE cliente_id=?', [$f['cliente_id']]) : [];
$preventivi = $f['cliente_id'] ? all('SELECT id, numero, oggetto FROM preventivi WHERE cliente_id=? ORDER BY data DESC', [$f['cliente_id']]) : [];
$cli = $f['cliente_id'] ? one('SELECT * FROM clienti WHERE id=?', [$f['cliente_id']]) : null;

layout_start($id ? 'Fattura ' . $f['numero'] : 'Registra fattura', 'fatture');
if ($id) { [$sk, $sl] = fatt_stato($f); }
page_head($id ? 'Fattura ' . $f['numero'] : 'Registra fattura', '', $id ? badge($sk, $sl) . ' <a href="' . url('cliente', ['id' => $f['cliente_id']]) . '">' . h($cli['nome_breve'] ?: $cli['ragione_sociale']) . '</a>' : '');
?>
<div class="cols">
<form method="post" class="card form col-main" enctype="multipart/form-data">
  <?= csrf() ?><input type="hidden" name="azione" value="salva">
  <div class="grid g3">
    <label class="span2">Cliente *<select name="cliente_id" required><?= clienti_options($f['cliente_id'] ? (int)$f['cliente_id'] : null, true, '— Scegli —') ?></select></label>
    <label>Numero<input name="numero" value="<?= h($f['numero']) ?>" required></label>
    <label>Data<input type="date" name="data" value="<?= h($f['data']) ?>" required></label>
    <label>Scadenza pagamento<input type="date" name="scadenza" value="<?= h($f['scadenza']) ?>"></label>
    <label>Descrizione<input name="descrizione" value="<?= h($f['descrizione']) ?>" placeholder="Es. Gestione social ottobre"></label>
    <label>Imponibile € *<input name="imponibile" inputmode="decimal" required value="<?= $f['imponibile'] === '' ? '' : num_it($f['imponibile']) ?>" data-calc></label>
    <label>IVA %<input name="iva_percentuale" inputmode="decimal" value="<?= num_it($f['iva_percentuale']) ?>" data-calc></label>
    <label>Ritenuta d'acconto % <span class="hint">0 se non c'è</span><input name="ritenuta_percentuale" inputmode="decimal" value="<?= num_it($f['ritenuta_percentuale']) ?>" data-calc></label>
  </div>
  <div class="calc-out" id="fatt-calc"></div>
  <details <?= ($f['preventivo_id'] || $f['contratto_id']) ? 'open' : '' ?>><summary>Collega a preventivo o gestione</summary>
    <div class="grid g2">
      <label>Preventivo<select name="preventivo_id"><option value="">— Nessuno —</option><?php foreach ($preventivi as $p): ?><option value="<?= $p['id'] ?>" <?= (int)$f['preventivo_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['numero'] . ' · ' . $p['oggetto']) ?></option><?php endforeach; ?></select></label>
      <label>Gestione<select name="contratto_id"><option value="">— Nessuna —</option><?php foreach ($contratti as $k): ?><option value="<?= $k['id'] ?>" <?= (int)$f['contratto_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= h($k['titolo']) ?></option><?php endforeach; ?></select></label>
    </div>
    <?php if (!$f['cliente_id']): ?><p class="muted small">Scegli prima il cliente e salva: poi potrai collegarla.</p><?php endif; ?>
  </details>
  <label>Note<textarea name="note" rows="2"><?= h($f['note']) ?></textarea></label>
  <label>Allega il PDF della fattura <span class="hint">facoltativo</span><input type="file" name="file" accept=".pdf,.xml,.p7m,image/*"></label>
  <?php if (!$id): ?><label class="check"><input type="checkbox" name="gia_pagata" value="1"> È già stata pagata</label><?php endif; ?>
  <?php if ($id): ?><label class="check"><input type="checkbox" name="annullata" value="1" <?= $f['annullata'] ? 'checked' : '' ?>> Annullata (es. sostituita da nota di credito)</label><?php endif; ?>
  <div class="form-actions">
    <button class="btn btn-primary">Salva</button>
    <a class="btn btn-ghost" href="<?= url('fatture') ?>">Annulla</a>
    <?php if ($id): ?><button class="link-btn danger push-right" name="azione" value="elimina" formnovalidate data-conferma="Eliminare la fattura e i suoi pagamenti?">Elimina</button><?php endif; ?>
  </div>
</form>

<?php if ($id): $res = round($f['totale'] - $f['pagato'], 2); ?>
<div class="col-side">
  <section class="card">
    <h2>Pagamenti</h2>
    <dl class="kv">
      <dt>Imponibile</dt><dd><?= money($f['imponibile']) ?></dd>
      <dt>IVA <?= num_it($f['iva_percentuale']) ?>%</dt><dd><?= money($f['iva']) ?></dd>
      <?php if ($f['ritenuta'] > 0): ?><dt>Ritenuta <?= num_it($f['ritenuta_percentuale']) ?>%</dt><dd>− <?= money($f['ritenuta']) ?></dd><?php endif; ?>
      <dt><b>Da ricevere</b></dt><dd><b><?= money($f['totale']) ?></b></dd>
      <dt>Incassato</dt><dd><?= money($f['pagato']) ?></dd>
      <dt><b>Residuo</b></dt><dd><b class="<?= $res > 0 ? 'accent' : '' ?>"><?= money(max(0, $res)) ?></b></dd>
    </dl>
    <?php foreach ($pagamenti as $pg): ?>
      <div class="mini-item"><?= icon('check') ?><span><?= d($pg['data']) ?> · <b><?= money($pg['importo']) ?></b> <?= h($pg['metodo']) ?> <?= h($pg['note']) ?></span>
        <form method="post" class="inline" data-conferma="Togliere questo pagamento?"><?= csrf() ?><input type="hidden" name="azione" value="elimina_pagamento"><input type="hidden" name="pid" value="<?= $pg['id'] ?>"><button class="icon-btn"><?= icon('trash') ?></button></form></div>
    <?php endforeach; ?>
    <?php if ($res > 0 && !$f['annullata']): ?>
    <form method="post" class="mini-form">
      <?= csrf() ?><input type="hidden" name="azione" value="pagamento">
      <div class="row"><input name="importo" inputmode="decimal" value="<?= num_it($res) ?>" aria-label="Importo"><input type="date" name="data" value="<?= date('Y-m-d') ?>" aria-label="Data"></div>
      <div class="row"><select name="metodo"><option>Bonifico</option><option>Carta</option><option>Contanti</option><option>PayPal</option><option>Altro</option></select><input name="note" placeholder="Note"></div>
      <button class="btn btn-ok btn-block">Registra incasso</button>
    </form>
    <?php if ($f['scadenza'] && $f['scadenza'] < date('Y-m-d')): ?>
      <form method="post" class="stack" style="margin-top:12px"><?= csrf() ?><input type="hidden" name="azione" value="sollecito">
        <p class="small"><span class="due is-late">Scaduta <?= rel_days($f['scadenza']) ?></span></p>
        <?php $ref = one('SELECT * FROM contatti WHERE cliente_id=? AND email IS NOT NULL ORDER BY principale DESC LIMIT 1', [$f['cliente_id']]); $to = $ref['email'] ?? $cli['email'];
        if ($to):
          $body = "Ciao" . ($ref ? ' ' . explode(' ', $ref['nome'])[0] : '') . ",\n\nti scrivo per ricordarti la fattura n. {$f['numero']} del " . d($f['data']) . " di " . money($res) . ", scaduta il " . d($f['scadenza']) . ".\nSe hai già provveduto, ignora pure questo messaggio.\n\nGrazie,\n" . (user()['nome'] ?? '') . "\n" . setting('azienda_nome', 'HypeBang'); ?>
          <a class="btn btn-block" href="mailto:<?= h($to) ?>?subject=<?= rawurlencode('Promemoria fattura ' . $f['numero']) ?>&body=<?= rawurlencode($body) ?>"><?= icon('mail') ?> Scrivi un sollecito</a>
        <?php endif; ?>
        <button class="link-btn">Segna "sollecitato" nello storico</button>
      </form>
    <?php endif; ?>
    <?php endif; ?>
  </section>
  <section class="card"><h2>File</h2><?= allegati_box('fattura', $id) ?></section>
</div>
<?php endif; ?>
</div>
<?php layout_end();
