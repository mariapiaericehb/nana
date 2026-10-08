<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$filtro = get('f', 'attivi');
$rows = all('SELECT k.*, ' . C_COLS . ' FROM contratti k JOIN clienti c ON c.id=k.cliente_id ' . ($filtro === 'attivi' ? "WHERE k.stato='attivo'" : ($filtro === 'chiusi' ? "WHERE k.stato<>'attivo'" : '')) . ' ORDER BY k.stato=\'attivo\' DESC, k.data_fine IS NULL, k.data_fine');
$mrr = 0; $anno = 0; $in_scad = 0; $da_fatt = [];
foreach ($rows as &$k) {
    $k['_fine'] = contr_scadenza($k);
    $k['_next'] = contr_prossima_fattura($k);
    if ($k['stato'] !== 'attivo') continue;
    $m = PERIODO_MESI[$k['periodicita']];
    if ($m > 0) { $mrr += $k['importo'] / $m; $anno += $k['importo'] * 12 / $m; }
    if ($k['_fine'] && days_until($k['_fine']) <= (int)$k['preavviso_giorni']) $in_scad++;
    if ($k['_next'] && days_until($k['_next']) <= 7) $da_fatt[] = $k;
}
unset($k);

layout_start('Gestioni', 'contratti');
page_head('Gestioni e servizi ricorrenti', '<a class="btn btn-primary" href="' . url('contratto', ['modifica' => 1]) . '">' . icon('plus') . ' Nuova gestione</a>');
?>
<div class="stats stats-3">
  <div class="stat"><span>Entrate ricorrenti</span><b><?= money($mrr, false) ?></b><small>al mese, dalle gestioni attive</small></div>
  <div class="stat"><span>Su base annua</span><b><?= money($anno, false) ?></b><small>se restano tutte attive</small></div>
  <div class="stat <?= $in_scad ? 'stat-alert' : '' ?>"><span>Da rinnovare o disdire</span><b><?= $in_scad ?></b><small>entro il periodo di preavviso</small></div>
</div>

<?php if ($da_fatt): ?>
<section class="card callout">
  <h2><?= icon('euro') ?> Da fatturare</h2>
  <?php foreach ($da_fatt as $k): ?>
    <div class="mini-item"><span><?= cliente_link($k) ?> · <?= h($k['titolo']) ?> · <b><?= money($k['importo']) ?></b> <span class="due <?= due_class($k['_next']) ?>"><?= d_short($k['_next']) ?></span></span>
      <a class="btn btn-sm" href="<?= url('fattura', ['nuova' => 1, 'contratto_id' => $k['id']]) ?>">Registra fattura</a></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="toolbar"><div class="tabs">
  <?php foreach (['attivi' => 'Attive', 'chiusi' => 'Concluse e disdette', 'tutti' => 'Tutte'] as $kk => $l): ?><a class="<?= $filtro === $kk ? 'on' : '' ?>" href="<?= url('contratti', ['f' => $kk]) ?>"><?= $l ?></a><?php endforeach; ?>
</div></div>

<?php if (!$rows): ?>
  <?= empty_state('Nessuna gestione.', '<a class="btn" href="' . url('contratto', ['modifica' => 1]) . '">Aggiungi una gestione</a>') ?>
<?php else: ?>
<div data-bulk><div class="card table-wrap"><table class="table">
  <thead><tr><?= sel_th() ?><th>Gestione</th><th>Cliente</th><th class="num">Importo</th><th class="hide-sm">Dal</th><th>Scadenza / rinnovo</th><th class="hide-sm">Prossima fattura</th><th>Stato</th></tr></thead><tbody>
  <?php foreach ($rows as $k):
    $alert = $k['stato'] === 'attivo' && $k['_fine'] && days_until($k['_fine']) <= (int)$k['preavviso_giorni']; ?>
  <tr class="row-link" data-href="<?= url('contratto', ['id' => $k['id']]) ?>">
    <?= sel_td((int)$k['id']) ?>
    <td><a href="<?= url('contratto', ['id' => $k['id']]) ?>"><b><?= h($k['titolo']) ?></b></a></td>
    <td><?= cliente_link($k) ?></td>
    <td class="num nowrap"><?= money($k['importo'], false) ?><div class="muted small"><?= mb_strtolower(PERIODICITA[$k['periodicita']]) ?></div></td>
    <td class="hide-sm nowrap"><?= d($k['data_inizio']) ?></td>
    <td class="nowrap"><?php if ($k['_fine']): ?><span class="due <?= $alert ? (days_until($k['_fine']) < 0 ? 'is-late' : 'is-soon') : '' ?>"><?= d($k['_fine']) ?></span>
      <div class="muted small"><?= $k['rinnovo_automatico'] ? 'si rinnova da solo' : 'non si rinnova' ?><?= $alert ? ' · ' . rel_days($k['_fine']) : '' ?></div><?php else: ?><span class="muted">senza scadenza</span><?php endif; ?></td>
    <td class="hide-sm nowrap"><?= $k['_next'] ? '<span class="due ' . due_class($k['_next']) . '">' . d($k['_next']) . '</span>' : '<span class="muted">—</span>' ?></td>
    <td><?= badge($k['stato'], STATI_CONTR[$k['stato']]) ?></td>
  </tr>
  <?php endforeach; ?>
</tbody></table></div>
<?= bulk_bar('contratti', [
  'concluso' => ['Segna come concluse'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Le fatture collegate restano. Non si può annullare.', 'danger'],
]) ?></div>
<?php endif;
layout_end();
