<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// Elenco task riutilizzabile. Si aspetta: $task_list (righe task, con eventuali c_* e progetto), $quick (campi nascosti per l'aggiunta rapida, oppure null)
$quick = $quick ?? null;
$show_client = $show_client ?? false;
$progetti_opt = $progetti_opt ?? null;
?>
<div class="tasks">
  <?php if ($quick !== null): ?>
  <form method="post" action="<?= url('azioni') ?>" class="quick-add">
    <?= csrf() ?><input type="hidden" name="azione" value="task_rapido">
    <?php foreach ($quick as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endforeach; ?>
    <input name="titolo" placeholder="Nuovo task…" required>
    <input type="date" name="scadenza" title="Scadenza">
    <select name="priorita" title="Priorità"><?= options(PRIORITA, 'media') ?></select>
    <button class="btn btn-sm btn-primary"><?= icon('plus') ?><span class="hide-sm">Aggiungi</span></button>
  </form>
  <?php endif; ?>
  <?php foreach ($task_list as $t): $done = $t['stato'] === 'fatto'; ?>
  <div class="task <?= $done ? 'done' : '' ?> pr-<?= h($t['priorita']) ?>">
    <?= sel_box((int)$t['id']) ?>
    <input type="checkbox" class="task-check" autocomplete="off" data-id="<?= $t['id'] ?>" <?= $done ? 'checked' : '' ?> aria-label="Fatto">
    <details class="task-body" data-task="<?= $t['id'] ?>" id="t<?= $t['id'] ?>">
      <summary>
        <span class="task-title"><?= h($t['titolo']) ?></span>
        <span class="task-meta">
          <?php if ($t['priorita'] === 'alta'): ?><span class="badge b-alta">Alta</span><?php endif; ?>
          <?php if ($t['stato'] === 'in_corso' || $t['stato'] === 'in_attesa'): ?><span class="badge b-<?= $t['stato'] ?>"><?= STATI_TASK[$t['stato']] ?></span><?php endif; ?>
          <?php if (!empty($t['personale'])): ?><span class="badge b-personale">★ Personale</span><?php endif; ?>
          <?php if ($show_client && !empty($t['c_id'])): ?><span class="small"><?= cliente_link($t) ?></span><?php endif; ?>
          <?php if (!empty($t['progetto'])): ?><span class="muted small"><?= h($t['progetto']) ?></span><?php endif; ?>
          <?php if ($t['scadenza']): ?><span class="due <?= due_class($t['scadenza'], $done) ?>"><?= icon('clock') ?><?= d_short($t['scadenza']) ?><?= $t['ora'] ? ' ' . t($t['ora']) : '' ?></span><?php endif; ?>
        </span>
      </summary>
      <form method="post" action="<?= url('azioni') ?>" class="task-edit">
        <?= csrf() ?><input type="hidden" name="azione" value="task_salva"><input type="hidden" name="id" value="<?= $t['id'] ?>">

        <label class="span-all">Titolo<input name="titolo" value="<?= h($t['titolo']) ?>" required></label>
        <label class="span-all">Dettagli<textarea name="descrizione" rows="3"><?= h($t['descrizione']) ?></textarea></label>
        <label>Stato<select name="stato"><?= options(STATI_TASK, $t['stato']) ?></select></label>
        <label>Priorità<select name="priorita"><?= options(PRIORITA, $t['priorita']) ?></select></label>
        <label>Scadenza<input type="date" name="scadenza" value="<?= h($t['scadenza']) ?>"></label>
        <label>Ora<input type="time" name="ora" value="<?= t($t['ora']) ?>"></label>
        <?php if (empty($t['progetto_id'])): ?><label class="span-all">Per chi<select name="cliente_id"><?= task_chi_options($t['cliente_id'] ? (int)$t['cliente_id'] : null, !empty($t['personale'])) ?></select></label>
        <?php else: ?><input type="hidden" name="cliente_id" value="<?= h($t['cliente_id']) ?>"><?php endif; ?>
        <?php if ($progetti_opt !== null): ?>
        <label class="span-all">Progetto<select name="progetto_id"><option value="">— Nessuno —</option><?php foreach ($progetti_opt as $po): ?><option value="<?= $po['id'] ?>" <?= (int)$t['progetto_id'] === (int)$po['id'] ? 'selected' : '' ?>><?= h($po['label']) ?></option><?php endforeach; ?></select></label>
        <?php else: ?><input type="hidden" name="progetto_id" value="<?= h($t['progetto_id']) ?>"><?php endif; ?>
        <div class="row span-all">
          <button class="btn btn-sm btn-primary">Salva</button>
          <button class="link-btn danger" name="azione" value="task_elimina" data-conferma="Eliminare il task?">Elimina</button>
        </div>
      </form>
    </details>
  </div>
  <?php endforeach; ?>
  <?php if (!$task_list): ?><p class="muted small empty-tasks">Nessun task.</p><?php endif; ?>
</div>
