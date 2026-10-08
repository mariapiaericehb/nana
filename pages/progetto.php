<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$p = $id ? one('SELECT * FROM progetti WHERE id=?', [$id]) : null;
if ($id && !$p) redirect(url('progetti'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $data = [
            'cliente_id' => id_or_null(post('cliente_id')), 'titolo' => post('titolo') ?: 'Progetto', 'descrizione' => nul(post('descrizione')),
            'stato' => pick(post('stato'), STATI_PROG, 'da_iniziare'), 'data_inizio' => date_or_null(post('data_inizio')),
            'scadenza' => date_or_null(post('scadenza')), 'budget' => post('budget') === '' ? null : dec(post('budget')),
        ];
        if ($id) {
            if ($p['stato'] !== $data['stato'] && $data['cliente_id']) log_act($data['cliente_id'], 'Progetto "' . $data['titolo'] . '": ' . mb_strtolower(STATI_PROG[$data['stato']]), url('progetto', ['id' => $id]));
            update('progetti', $id, $data);
            q('UPDATE task SET cliente_id=? WHERE progetto_id=?', [$data['cliente_id'], $id]);
        } else {
            $id = insert('progetti', $data);
            if ($data['cliente_id']) log_act($data['cliente_id'], 'Nuovo progetto: ' . $data['titolo'], url('progetto', ['id' => $id]));
        }
        flash('Progetto salvato.');
        redirect(url('progetto', ['id' => $id]));
    }
    if ($p && $az === 'elimina') {
        delete_row('progetti', $id);
        flash('Progetto eliminato con i suoi task.');
        redirect(url('progetti'));
    }
}

if (!$p || get('modifica')) {
    $p = $p ?? ['cliente_id' => (int)get('cliente_id') ?: null, 'titolo' => '', 'descrizione' => '', 'stato' => 'da_iniziare', 'data_inizio' => date('Y-m-d'), 'scadenza' => '', 'budget' => null];
    layout_start($id ? 'Modifica progetto' : 'Nuovo progetto', 'progetti');
    page_head($id ? 'Modifica progetto' : 'Nuovo progetto');
    ?>
    <form method="post" class="card form narrow">
      <?= csrf() ?><input type="hidden" name="azione" value="salva">
      <div class="grid g2">
        <label class="span2">Nome del progetto *<input name="titolo" required value="<?= h($p['titolo']) ?>" placeholder="Es. Lancio nuova collezione"></label>
        <label>Cliente<select name="cliente_id"><?= clienti_options($p['cliente_id'] ? (int)$p['cliente_id'] : null, true, '— Progetto interno —') ?></select></label>
        <label>Stato<select name="stato"><?= options(STATI_PROG, $p['stato']) ?></select></label>
        <label>Inizio<input type="date" name="data_inizio" value="<?= h($p['data_inizio']) ?>"></label>
        <label>Consegna<input type="date" name="scadenza" value="<?= h($p['scadenza']) ?>"></label>
        <label>Budget € <span class="hint">facoltativo</span><input name="budget" inputmode="decimal" value="<?= $p['budget'] !== null ? num_it($p['budget']) : '' ?>"></label>
        <label class="span2">Descrizione / brief<textarea name="descrizione" rows="6"><?= h($p['descrizione']) ?></textarea></label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary">Salva</button>
        <a class="btn btn-ghost" href="<?= $id ? url('progetto', ['id' => $id]) : url('progetti') ?>">Annulla</a>
        <?php if ($id): ?><button class="link-btn danger push-right" name="azione" value="elimina" formnovalidate data-conferma="Eliminare il progetto e tutti i suoi task?">Elimina</button><?php endif; ?>
      </div>
    </form>
    <?php layout_end();
    return;
}

$c = $p['cliente_id'] ? one('SELECT * FROM clienti WHERE id=?', [$p['cliente_id']]) : null;
$tasks = all('SELECT * FROM task WHERE progetto_id=? ORDER BY ordine, id', [$id]);
$by = array_fill_keys(array_keys(STATI_TASK), []);
foreach ($tasks as $t) $by[$t['stato']][] = $t;
$fatti = count($by['fatto']); $tot = count($tasks);
$vista = get('vista', 'bacheca');

layout_start($p['titolo'], 'progetti');
$sub = badge($p['stato'], STATI_PROG[$p['stato']]) . ' ' . ($c ? '<a href="' . url('cliente', ['id' => $c['id'], 'tab' => 'lavoro']) . '">' . h($c['nome_breve'] ?: $c['ragione_sociale']) . '</a>' : '<span class="muted">Interno</span>');
if ($p['scadenza']) $sub .= ' · consegna <span class="due ' . due_class($p['scadenza'], in_array($p['stato'], ['completato','annullato'])) . '">' . d($p['scadenza']) . '</span>';
page_head($p['titolo'], sel_toggle('più task') . ' <a class="btn" href="' . url('progetto', ['id' => $id, 'modifica' => 1]) . '">' . icon('edit') . ' Modifica</a>', $sub);
?>
<div class="proj-top">
  <div class="bar big"><i style="width:<?= $tot ? round($fatti / $tot * 100) : 0 ?>%"></i></div>
  <span class="small muted"><?= $fatti ?> di <?= $tot ?> task fatti<?= $p['budget'] ? ' · budget ' . money($p['budget'], false) : '' ?></span>
  <div class="tabs tabs-sm push-right">
    <a class="<?= $vista === 'bacheca' ? 'on' : '' ?>" href="<?= url('progetto', ['id' => $id]) ?>">Bacheca</a>
    <a class="<?= $vista === 'lista' ? 'on' : '' ?>" href="<?= url('progetto', ['id' => $id, 'vista' => 'lista']) ?>">Lista</a>
    <a class="<?= $vista === 'info' ? 'on' : '' ?>" href="<?= url('progetto', ['id' => $id, 'vista' => 'info']) ?>">Brief e file</a>
  </div>
</div>

<div data-bulk>
<?php if ($vista === 'bacheca'): ?>
<form method="post" action="<?= url('azioni') ?>" class="quick-add card">
  <?= csrf() ?><input type="hidden" name="azione" value="task_rapido"><input type="hidden" name="progetto_id" value="<?= $id ?>">
  <input name="titolo" placeholder="Aggiungi un task al progetto…" required>
  <input type="date" name="scadenza" title="Scadenza"><select name="priorita"><?= options(PRIORITA, 'media') ?></select>
  <button class="btn btn-sm btn-primary"><?= icon('plus') ?><span class="hide-sm">Aggiungi</span></button>
</form>
<div class="board board-4" data-board="task">
  <?php foreach (STATI_TASK as $k => $label): ?>
  <div class="col col-<?= $k ?>" data-col="<?= $k ?>">
    <div class="col-head"><b><?= $label ?></b><span><?= count($by[$k]) ?></span></div>
    <div class="col-body">
      <?php foreach ($by[$k] as $t): ?>
      <div class="kcard pr-<?= $t['priorita'] ?>" draggable="true" data-id="<?= $t['id'] ?>">
        <?= sel_box((int)$t['id']) ?>
        <a class="kcard-title" href="<?= url('task', ['apri' => $t['id']]) ?>#t<?= $t['id'] ?>"><?= h($t['titolo']) ?></a>
        <?php if ($t['descrizione']): ?><div class="muted small clamp"><?= h($t['descrizione']) ?></div><?php endif; ?>
        <div class="kcard-foot">
          <?php if ($t['priorita'] === 'alta'): ?><span class="badge b-alta">Alta</span><?php endif; ?>
          <?php if ($t['scadenza']): ?><span class="due <?= due_class($t['scadenza'], $k === 'fatto') ?>"><?= icon('clock') ?><?= d_short($t['scadenza']) ?></span><?php endif; ?>
        </div>
        <select class="move-select" aria-label="Sposta in"><?php foreach (STATI_TASK as $sk => $sl): ?><option value="<?= $sk ?>" <?= $sk === $k ? 'selected' : '' ?>><?= $sk === $k ? 'Sposta in…' : $sl ?></option><?php endforeach; ?></select>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif ($vista === 'lista'): ?>
<section class="card">
  <?php $task_list = $tasks; usort($task_list, fn($a, $b) => [$a['stato'] === 'fatto', $a['scadenza'] ?? '9999', $a['ordine']] <=> [$b['stato'] === 'fatto', $b['scadenza'] ?? '9999', $b['ordine']]);
  $quick = ['progetto_id' => $id]; require APP_ROOT . '/pages/_task_list.php'; ?>
</section>
<?php else: ?>
<div class="cols">
  <section class="card col-main"><h2>Brief</h2>
    <?= $p['descrizione'] ? '<div class="pre">' . h($p['descrizione']) . '</div>' : '<p class="muted">Nessuna descrizione. <a href="' . url('progetto', ['id' => $id, 'modifica' => 1]) . '">Aggiungila</a></p>' ?>
    <?php if ($p['preventivo_id']): ?><p class="small"><a href="<?= url('preventivo', ['id' => $p['preventivo_id']]) ?>">Vedi il preventivo di origine →</a></p><?php endif; ?>
  </section>
  <section class="card col-side"><h2>File</h2><?= allegati_box('progetto', $id) ?></section>
</div>
<?php endif; ?>
<?= bulk_bar('task', [
  'fatto' => ['Segna come fatti'],
  'sposta' => ['Sposta', '', '', '<input type="date" name="nuova_data" class="bulk-date" aria-label="Nuova data">'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Non si può annullare.', 'danger'],
]) ?>
</div>
<?php layout_end();
