<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $cid = id_or_null(post('cliente_id'));
        // nuovo potenziale cliente al volo
        if (!$cid && post('nuovo_cliente') !== '') {
            $cid = insert('clienti', ['ragione_sociale' => post('nuovo_cliente'), 'stato' => 'potenziale']);
            log_act($cid, 'Scheda creata da una nuova trattativa');
        }
        if (!$cid) { flash('Scegli un cliente o scrivi il nome di uno nuovo.', 'err'); back(); }
        $fase = pick(post('fase'), FASI, 'nuova');
        $data = [
            'cliente_id' => $cid, 'titolo' => post('titolo') ?: 'Nuova opportunità', 'valore' => dec(post('valore')),
            'fase' => $fase, 'probabilita' => max(0, min(100, (int)post('probabilita', FASI_PROB[$fase]))),
            'chiusura_prevista' => date_or_null(post('chiusura_prevista')),
            'prossimo_passo' => nul(post('prossimo_passo')), 'prossimo_passo_data' => date_or_null(post('prossimo_passo_data')),
            'motivo_perso' => $fase === 'persa' ? nul(post('motivo_perso')) : null, 'note' => nul(post('note')),
        ];
        if ($id) {
            $old = one('SELECT * FROM trattative WHERE id=?', [$id]);
            update('trattative', $id, $data);
            if ($old['fase'] !== $fase) log_act($cid, 'Trattativa "' . $data['titolo'] . '": ' . FASI[$old['fase']] . ' → ' . FASI[$fase] . ($data['motivo_perso'] ? ' (' . $data['motivo_perso'] . ')' : ''), url('trattative', ['id' => $id]));
        } else {
            $id = insert('trattative', $data);
            log_act($cid, 'Nuova trattativa: ' . $data['titolo'] . ' (' . money($data['valore'], false) . ')', url('trattative', ['id' => $id]));
        }
        if ($fase === 'vinta') q("UPDATE clienti SET stato='cliente' WHERE id=? AND stato='potenziale'", [$cid]);
        flash('Trattativa salvata.');
        redirect(url('trattative'));
    }
    if ($az === 'elimina' && $id) {
        delete_row('trattative', $id);
        flash('Trattativa eliminata.');
        redirect(url('trattative'));
    }
}

// ---------- Modulo ----------
if ($id || get('nuova')) {
    $t = $id ? one('SELECT * FROM trattative WHERE id=?', [$id]) : ['cliente_id' => (int)get('cliente_id') ?: null, 'fase' => 'nuova', 'probabilita' => 10];
    if (!$t) redirect(url('trattative'));
    $preventivi = $id ? all('SELECT * FROM preventivi WHERE trattativa_id=? ORDER BY data DESC', [$id]) : [];
    layout_start($id ? $t['titolo'] : 'Nuova trattativa', 'trattative');
    page_head($id ? $t['titolo'] : 'Nuova trattativa', $id ? '<a class="btn" href="' . url('preventivo', ['nuovo' => 1, 'cliente_id' => $t['cliente_id'], 'trattativa_id' => $id]) . '">' . icon('doc') . ' Crea preventivo</a>' : '');
    ?>
    <div class="cols">
    <form method="post" class="card form col-main">
      <?= csrf() ?><input type="hidden" name="azione" value="salva">
      <div class="grid g2">
        <label class="span2">Cosa stai proponendo *<input name="titolo" required value="<?= h($t['titolo'] ?? '') ?>" placeholder="Es. Gestione social 6 mesi"></label>
        <label>Cliente<select name="cliente_id" id="cliente_sel"><?= clienti_options($t['cliente_id'] ? (int)$t['cliente_id'] : null, true, '— Scegli o crea sotto —') ?></select></label>
        <label>…oppure nuovo potenziale cliente<input name="nuovo_cliente" placeholder="Nome azienda"></label>
        <label>Valore stimato (€)<input name="valore" inputmode="decimal" value="<?= isset($t['valore']) ? num_it($t['valore']) : '' ?>"></label>
        <label>Fase<select name="fase" data-prob><?= options(FASI, $t['fase']) ?></select></label>
        <label>Probabilità (%)<input type="number" name="probabilita" min="0" max="100" value="<?= (int)$t['probabilita'] ?>"></label>
        <label>Chiusura prevista<input type="date" name="chiusura_prevista" value="<?= h($t['chiusura_prevista'] ?? '') ?>"></label>
        <label>Prossimo passo<input name="prossimo_passo" value="<?= h($t['prossimo_passo'] ?? '') ?>" placeholder="Richiamare, mandare proposta…"></label>
        <label>Entro il<input type="date" name="prossimo_passo_data" value="<?= h($t['prossimo_passo_data'] ?? '') ?>"></label>
        <label class="span2 if-persa">Perché è persa?<input name="motivo_perso" value="<?= h($t['motivo_perso'] ?? '') ?>" placeholder="Prezzo, tempi, ha scelto un altro…"></label>
        <label class="span2">Note<textarea name="note" rows="4"><?= h($t['note'] ?? '') ?></textarea></label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary">Salva</button>
        <a class="btn btn-ghost" href="<?= url('trattative') ?>">Annulla</a>
        <?php if ($id): ?><button class="link-btn danger push-right" name="azione" value="elimina" formnovalidate data-conferma="Eliminare la trattativa?">Elimina</button><?php endif; ?>
      </div>
    </form>
    <?php if ($id): ?>
    <div class="col-side">
      <section class="card">
        <h2>Preventivi collegati</h2>
        <?php foreach ($preventivi as $p): ?>
          <div class="mini-item"><?= icon('doc') ?><span><a href="<?= url('preventivo', ['id' => $p['id']]) ?>"><?= h($p['numero']) ?> · <?= h($p['oggetto']) ?></a> <?= badge($p['stato'], STATI_PREV[$p['stato']]) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$preventivi): ?><p class="muted small">Nessuno. Quando sei pronta, crea il preventivo da qui: cliente e oggetto sono già compilati.</p><?php endif; ?>
      </section>
    </div>
    <?php endif; ?>
    </div>
    <script>window.FASI_PROB = <?= json_encode(FASI_PROB) ?>;</script>
    <?php layout_end();
    return;
}

// ---------- Pipeline ----------
$rows = all("SELECT t.*, " . C_COLS . " FROM trattative t JOIN clienti c ON c.id=t.cliente_id
  WHERE t.fase NOT IN ('vinta','persa') OR t.updated_at > NOW() - INTERVAL 60 DAY
  ORDER BY t.ordine, t.updated_at DESC");
$by = array_fill_keys(array_keys(FASI), []);
foreach ($rows as $r) $by[$r['fase']][] = $r;
$aperto = 0; $ponderato = 0;
foreach ($rows as $r) if (!in_array($r['fase'], ['vinta','persa'])) { $aperto += $r['valore']; $ponderato += $r['valore'] * $r['probabilita'] / 100; }
$anno = one("SELECT SUM(fase='vinta') v, SUM(fase='persa') p, SUM(CASE WHEN fase='vinta' THEN valore ELSE 0 END) vv FROM trattative WHERE YEAR(updated_at)=YEAR(CURDATE())");
$tasso = ($anno['v'] + $anno['p']) > 0 ? round($anno['v'] / ($anno['v'] + $anno['p']) * 100) : null;

layout_start('Trattative', 'trattative');
page_head('Trattative', sel_toggle('più trattative') . ' <a class="btn btn-primary" href="' . url('trattative', ['nuova' => 1]) . '">' . icon('plus') . ' Nuova trattativa</a>');
?>
<div class="stats stats-4">
  <div class="stat"><span>In ballo</span><b><?= money($aperto, false) ?></b><small><?= count(array_filter($rows, fn($r) => !in_array($r['fase'], ['vinta','persa']))) ?> trattative aperte</small></div>
  <div class="stat"><span>Valore probabile</span><b><?= money($ponderato, false) ?></b><small>pesato sulle probabilità</small></div>
  <div class="stat"><span>Vinte nel <?= date('Y') ?></span><b><?= money($anno['vv'] ?? 0, false) ?></b><small><?= (int)$anno['v'] ?> trattative</small></div>
  <div class="stat"><span>Tasso di chiusura</span><b><?= $tasso === null ? '—' : $tasso . '%' ?></b><small><?= (int)$anno['v'] ?> vinte, <?= (int)$anno['p'] ?> perse</small></div>
</div>
<div data-bulk>
<div class="board" data-board="trattative">
  <?php foreach (FASI as $k => $label): $tot = array_sum(array_column($by[$k], 'valore')); ?>
  <div class="col col-<?= $k ?>" data-col="<?= $k ?>">
    <div class="col-head"><b><?= $label ?></b><span><?= count($by[$k]) ?><?= $tot ? ' · ' . money($tot, false) : '' ?></span></div>
    <div class="col-body">
      <?php foreach ($by[$k] as $t): ?>
      <div class="kcard" draggable="true" data-id="<?= $t['id'] ?>">
        <?= sel_box((int)$t['id']) ?>
        <a class="kcard-title" href="<?= url('trattative', ['id' => $t['id']]) ?>"><?= h($t['titolo']) ?></a>
        <div class="small"><?= cliente_link($t) ?></div>
        <div class="kcard-foot">
          <b><?= money($t['valore'], false) ?></b>
          <?php if ($t['prossimo_passo_data'] && !in_array($k, ['vinta','persa'])): ?><span class="due <?= due_class($t['prossimo_passo_data']) ?>" title="<?= h($t['prossimo_passo']) ?>"><?= icon('clock') ?><?= d_short($t['prossimo_passo_data']) ?></span><?php endif; ?>
        </div>
        <?php if ($t['prossimo_passo'] && !in_array($k, ['vinta','persa'])): ?><div class="muted small">→ <?= h($t['prossimo_passo']) ?></div><?php endif; ?>
        <select class="move-select" aria-label="Sposta in"><?php foreach (FASI as $fk => $fl): ?><option value="<?= $fk ?>" <?= $fk === $k ? 'selected' : '' ?>><?= $fk === $k ? 'Sposta in…' : $fl ?></option><?php endforeach; ?></select>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?= bulk_bar('trattative', [
  'persa' => ['Segna come perse'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Non si può annullare.', 'danger'],
]) ?>
</div>
<p class="muted small">Trascina le schede da una colonna all'altra (da telefono usa "Sposta in…"). Le trattative chiuse spariscono dalla bacheca dopo 60 giorni, ma restano nella scheda del cliente.</p>
<?php layout_end();
