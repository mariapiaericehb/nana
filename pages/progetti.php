<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$f = get('f', 'attivi');
$w = match ($f) { 'chiusi' => "p.stato IN ('completato','annullato')", 'tutti' => '1', default => "p.stato IN ('da_iniziare','in_corso','in_pausa')" };
$rows = all("SELECT p.*, " . C_COLS . ",
  (SELECT COUNT(*) FROM task WHERE progetto_id=p.id) tot,
  (SELECT COUNT(*) FROM task WHERE progetto_id=p.id AND stato='fatto') fatti,
  (SELECT MIN(scadenza) FROM task WHERE progetto_id=p.id AND stato<>'fatto') prossima
  FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE $w
  ORDER BY FIELD(p.stato,'in_corso','da_iniziare','in_pausa','completato','annullato'), p.scadenza IS NULL, p.scadenza");

layout_start('Progetti', 'progetti');
page_head('Progetti', '<a class="btn btn-primary" href="' . url('progetto', ['modifica' => 1]) . '">' . icon('plus') . ' Nuovo progetto</a>');
?>
<div class="toolbar"><div class="tabs">
  <?php foreach (['attivi' => 'In lavorazione', 'chiusi' => 'Chiusi', 'tutti' => 'Tutti'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= url('progetti', ['f' => $k]) ?>"><?= $l ?></a><?php endforeach; ?>
</div></div>
<?php if (!$rows): ?>
  <?= empty_state('Nessun progetto qui.', '<a class="btn" href="' . url('progetto', ['modifica' => 1]) . '">Crea un progetto</a>') ?>
<?php else: ?>
<div data-bulk>
<div class="row" style="justify-content:flex-end;margin:-6px 0 10px"><?= sel_toggle('più progetti') ?></div>
<div class="proj-grid">
<?php foreach ($rows as $p): $pct = $p['tot'] ? round($p['fatti'] / $p['tot'] * 100) : 0; $chiuso = in_array($p['stato'], ['completato','annullato']); ?>
  <a class="proj-card" href="<?= url('progetto', ['id' => $p['id']]) ?>" <?= $p['c_colore'] ? 'style="--pc:' . h($p['c_colore']) . '"' : '' ?>>
    <?= sel_box((int)$p['id']) ?>
    <div class="row-between"><span class="small"><?= h(cliente_nome($p) ?: 'Interno') ?></span><?= badge($p['stato'], STATI_PROG[$p['stato']]) ?></div>
    <b class="proj-title"><?= h($p['titolo']) ?></b>
    <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
    <div class="row-between small">
      <span class="muted"><?= $p['fatti'] ?>/<?= $p['tot'] ?> task</span>
      <?php if ($p['scadenza']): ?><span class="due <?= due_class($p['scadenza'], $chiuso) ?>">consegna <?= d_short($p['scadenza']) ?></span><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?= bulk_bar('progetti', [
  'completato' => ['Segna come completati'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Si cancellano anche i loro task. Non si può annullare.', 'danger'],
]) ?>
</div>
<?php endif;
layout_end();
