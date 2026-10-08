<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$anno = (int)get('anno', date('Y'));
$filtro = get('f', 'tutte');
$cliente = (int)get('cliente_id');
$rows = all('SELECT ' . FATT_COLS . ', ' . C_COLS . ' FROM fatture f JOIN clienti c ON c.id=f.cliente_id WHERE YEAR(f.data)=?' . ($cliente ? ' AND f.cliente_id=' . $cliente : '') . ' ORDER BY f.data DESC, f.id DESC', [$anno]);
// le fatture aperte degli anni precedenti contano comunque
$aperte_vecchie = all('SELECT ' . FATT_COLS . ', ' . C_COLS . ' FROM fatture f JOIN clienti c ON c.id=f.cliente_id WHERE YEAR(f.data)<? AND f.annullata=0 HAVING totale - pagato > 0.009 ORDER BY f.data', [$anno]);

$fatturato = 0; $incassato_anno = 0; $da_incassare = 0; $scaduto = 0;
$mesi = array_fill(1, 12, 0);
foreach ($rows as $f) {
    if ($f['annullata']) continue;
    $fatturato += $f['imponibile'];
    $mesi[(int)substr($f['data'], 5, 2)] += $f['imponibile'];
}
foreach (array_merge($rows, $aperte_vecchie) as $f) {
    if ($f['annullata']) continue;
    $res = max(0, $f['totale'] - $f['pagato']);
    $da_incassare += $res;
    if ($res > 0.009 && $f['scadenza'] && $f['scadenza'] < date('Y-m-d')) $scaduto += $res;
}
$incassato_anno = (float)val('SELECT COALESCE(SUM(pg.importo),0) FROM pagamenti pg JOIN fatture f ON f.id=pg.fattura_id WHERE f.annullata=0 AND YEAR(pg.data)=?', [$anno]);
$prec = (float)val('SELECT COALESCE(SUM(imponibile),0) FROM fatture WHERE annullata=0 AND YEAR(data)=?', [$anno - 1]);

$lista = $rows;
if ($filtro !== 'tutte') {
    $lista = array_filter(array_merge($aperte_vecchie, $rows), function ($f) use ($filtro) {
        [$k] = fatt_stato($f);
        return match ($filtro) {
            'aperte' => in_array($k, ['aperta', 'parziale', 'scaduta']),
            'scadute' => $k === 'scaduta',
            'pagate' => $k === 'pagata',
            default => true,
        };
    });
}
$anni = array_column(all('SELECT DISTINCT YEAR(data) y FROM fatture ORDER BY y DESC'), 'y');
if (!in_array(date('Y'), $anni)) array_unshift($anni, date('Y'));
$max = max($mesi) ?: 1;

layout_start('Fatture', 'fatture');
page_head('Fatture', '<a class="btn" href="' . url('esporta', ['cosa' => 'fatture', 'anno' => $anno]) . '">' . icon('download') . ' CSV per il commercialista</a> <a class="btn btn-primary" href="' . url('fattura', ['nuova' => 1]) . '">' . icon('plus') . ' Registra fattura</a>');
?>
<div class="stats stats-4">
  <div class="stat"><span>Fatturato <?= $anno ?></span><b><?= money($fatturato, false) ?></b><small><?php if ($prec > 0): $diff = round(($fatturato - $prec) / $prec * 100); ?><?= $diff >= 0 ? '+' : '' ?><?= $diff ?>% sul <?= $anno - 1 ?> (<?= money($prec, false) ?>)<?php else: ?>imponibile, IVA esclusa<?php endif; ?></small></div>
  <div class="stat"><span>Incassato nel <?= $anno ?></span><b><?= money($incassato_anno, false) ?></b><small>pagamenti ricevuti</small></div>
  <div class="stat"><span>Da incassare</span><b><?= money($da_incassare) ?></b><small>tutte le fatture aperte</small></div>
  <div class="stat <?= $scaduto > 0 ? 'stat-alert' : '' ?>"><span>Scaduto</span><b><?= money($scaduto) ?></b><small><?= $scaduto > 0 ? '<a href="' . url('fatture', ['anno' => $anno, 'f' => 'scadute']) . '">vedi chi deve pagare →</a>' : 'nessun ritardo' ?></small></div>
</div>

<section class="card chart-card">
  <div class="card-head"><h2>Fatturato mese per mese</h2><span class="muted small">imponibile <?= $anno ?></span></div>
  <div class="months">
    <?php foreach ($mesi as $m => $v): $cur = ($anno == date('Y') && $m == date('n')); ?>
      <div class="month <?= $cur ? 'cur' : '' ?>" title="<?= ucfirst(MESI[$m]) ?>: <?= money($v, false) ?>">
        <span class="mv"><?= $v > 0 ? number_format($v / 1000, $v < 10000 ? 1 : 0, ',', '.') . 'k' : '' ?></span>
        <i style="height:<?= round($v / $max * 100) ?>%"></i>
        <span class="ml"><?= substr(MESI[$m], 0, 3) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="toolbar">
  <div class="tabs">
    <?php foreach (['tutte' => 'Tutte', 'aperte' => 'Da incassare', 'scadute' => 'Scadute', 'pagate' => 'Pagate'] as $k => $l): ?>
      <a class="<?= $filtro === $k ? 'on' : '' ?>" href="<?= url('fatture', ['anno' => $anno, 'f' => $k]) ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="filter"><input type="hidden" name="p" value="fatture"><input type="hidden" name="f" value="<?= h($filtro) ?>">
    <select name="anno" onchange="this.form.submit()"><?php foreach ($anni as $y): ?><option <?= $y == $anno ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></form>
</div>

<?php if (!$lista): ?>
  <?= empty_state('Nessuna fattura qui.', '<a class="btn" href="' . url('fattura', ['nuova' => 1]) . '">Registra una fattura</a>') ?>
<?php else: ?>
<div data-bulk><div class="card table-wrap"><table class="table">
  <thead><tr><?= sel_th() ?><th>N.</th><th>Cliente</th><th class="hide-sm">Data</th><th>Scadenza</th><th class="num hide-sm">Imponibile</th><th class="num">Totale</th><th class="num">Residuo</th><th>Stato</th></tr></thead><tbody>
  <?php foreach ($lista as $f): [$sk, $sl] = fatt_stato($f); $res = max(0, $f['totale'] - $f['pagato']); ?>
  <tr class="row-link <?= $f['annullata'] ? 'is-void' : '' ?>" data-href="<?= url('fattura', ['id' => $f['id']]) ?>">
    <?= sel_td((int)$f['id']) ?>
    <td class="nowrap"><a href="<?= url('fattura', ['id' => $f['id']]) ?>"><b><?= h($f['numero']) ?></b></a><?= substr($f['data'], 0, 4) != $anno ? ' <span class="muted small">' . substr($f['data'], 0, 4) . '</span>' : '' ?></td>
    <td><?= cliente_link($f) ?><div class="muted small hide-sm"><?= h($f['descrizione']) ?></div></td>
    <td class="hide-sm nowrap"><?= d($f['data']) ?></td>
    <td class="nowrap"><?= $f['scadenza'] ? '<span class="due ' . ($res > 0.009 && !$f['annullata'] ? due_class($f['scadenza']) : '') . '">' . d($f['scadenza']) . '</span>' : '—' ?></td>
    <td class="num hide-sm"><?= money($f['imponibile']) ?></td>
    <td class="num"><?= money($f['totale']) ?></td>
    <td class="num"><?= $f['annullata'] ? '—' : ($res > 0.009 ? '<b>' . money($res) . '</b>' : '<span class="muted">0</span>') ?></td>
    <td><?= badge($sk, $sl) ?></td>
  </tr>
  <?php endforeach; ?>
</tbody></table></div>
<?= bulk_bar('fatture', [
  'pagata' => ['Segna come pagate oggi', 'Registrare come incassato oggi il residuo delle fatture selezionate ({n})?'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Si cancellano anche i pagamenti registrati. Non si può annullare.', 'danger'],
]) ?></div>
<?php endif; ?>
<p class="muted small">Qui tieni traccia di fatture e incassi. La fattura elettronica vera e propria va emessa con il tuo programma di fatturazione o dal portale dell'Agenzia delle Entrate: puoi allegare qui il PDF.</p>
<?php layout_end();
