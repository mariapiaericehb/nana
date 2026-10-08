<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$stato = get('stato');
$anno = (int)get('anno', date('Y'));
$par = [$anno]; $w = 'YEAR(p.data)=?';
if (isset(STATI_PREV[$stato])) { $w .= ' AND p.stato=?'; $par[] = $stato; }
$rows = all("SELECT p.*, " . C_COLS . ",
  (SELECT COALESCE(SUM(quantita*prezzo),0) FROM preventivo_righe WHERE preventivo_id=p.id) - p.sconto AS imponibile
  FROM preventivi p JOIN clienti c ON c.id=p.cliente_id WHERE $w ORDER BY p.data DESC, p.id DESC", $par);
$anni = array_column(all('SELECT DISTINCT YEAR(data) y FROM preventivi ORDER BY y DESC'), 'y') ?: [date('Y')];
if (!in_array(date('Y'), $anni)) array_unshift($anni, date('Y'));

$sum = ['inviato' => 0, 'accettato' => 0, 'rifiutato' => 0, 'n_acc' => 0, 'n_chiusi' => 0];
foreach (all("SELECT p.stato, (SELECT COALESCE(SUM(quantita*prezzo),0) FROM preventivo_righe WHERE preventivo_id=p.id) - p.sconto imp FROM preventivi p WHERE YEAR(p.data)=?", [$anno]) as $r) {
    if (isset($sum[$r['stato']])) $sum[$r['stato']] += $r['imp'];
    if ($r['stato'] === 'accettato') $sum['n_acc']++;
    if (in_array($r['stato'], ['accettato', 'rifiutato'])) $sum['n_chiusi']++;
}

layout_start('Preventivi', 'preventivi');
page_head('Preventivi', '<a class="btn btn-primary" href="' . url('preventivo', ['nuovo' => 1]) . '">' . icon('plus') . ' Nuovo preventivo</a>');
?>
<div class="stats stats-3">
  <div class="stat"><span>In attesa di risposta</span><b><?= money($sum['inviato'], false) ?></b><small>preventivi inviati</small></div>
  <div class="stat"><span>Accettati nel <?= $anno ?></span><b><?= money($sum['accettato'], false) ?></b><small><?= $sum['n_acc'] ?> preventivi</small></div>
  <div class="stat"><span>Tasso di accettazione</span><b><?= $sum['n_chiusi'] ? round($sum['n_acc'] / $sum['n_chiusi'] * 100) . '%' : '—' ?></b><small>su quelli con risposta</small></div>
</div>
<div class="toolbar">
  <div class="tabs">
    <a class="<?= !$stato ? 'on' : '' ?>" href="<?= url('preventivi', ['anno' => $anno]) ?>">Tutti</a>
    <?php foreach (STATI_PREV as $k => $l): ?><a class="<?= $stato === $k ? 'on' : '' ?>" href="<?= url('preventivi', ['anno' => $anno, 'stato' => $k]) ?>"><?= $l ?></a><?php endforeach; ?>
  </div>
  <form method="get" class="filter"><input type="hidden" name="p" value="preventivi"><input type="hidden" name="stato" value="<?= h($stato) ?>">
    <select name="anno" onchange="this.form.submit()"><?php foreach ($anni as $y): ?><option <?= $y == $anno ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></form>
</div>
<?php if (!$rows): ?>
  <?= empty_state('Nessun preventivo.', '<a class="btn" href="' . url('preventivo', ['nuovo' => 1]) . '">Crea il primo</a>') ?>
<?php else: ?>
<div data-bulk><div class="card table-wrap"><table class="table">
  <thead><tr><?= sel_th() ?><th>N.</th><th>Cliente</th><th>Oggetto</th><th class="hide-sm">Data</th><th>Stato</th><th class="num">Imponibile</th></tr></thead><tbody>
  <?php foreach ($rows as $p):
    $scade = date('Y-m-d', strtotime($p['data'] . ' +' . (int)$p['validita_giorni'] . ' days'));
    $scaduto = $p['stato'] === 'inviato' && $scade < date('Y-m-d'); ?>
  <tr class="row-link" data-href="<?= url('preventivo', ['id' => $p['id']]) ?>">
    <?= sel_td((int)$p['id']) ?>
    <td class="nowrap"><?= h($p['numero']) ?></td><td><?= cliente_link($p) ?></td>
    <td><a href="<?= url('preventivo', ['id' => $p['id']]) ?>"><?= h($p['oggetto']) ?></a></td>
    <td class="hide-sm nowrap"><?= d($p['data']) ?></td>
    <td><?= badge($p['stato'], STATI_PREV[$p['stato']]) ?><?= $scaduto ? ' <span class="due is-late small">validità scaduta</span>' : '' ?></td>
    <td class="num"><?= money($p['imponibile']) ?></td>
  </tr>
  <?php endforeach; ?>
</tbody></table></div>
<?= bulk_bar('preventivi', [
  'rifiutato' => ['Segna come rifiutati'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Non si può annullare.', 'danger'],
]) ?></div>
<?php endif;
layout_end();
