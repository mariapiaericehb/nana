<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$stato = get('stato', 'attivi');
$s = get('s');
$where = [];
$par = [];
if ($stato === 'attivi') $where[] = "c.stato='cliente'";
elseif (isset(STATI_CLIENTE[$stato])) { $where[] = 'c.stato=?'; $par[] = $stato; }
if ($s !== '') {
    $where[] = '(c.ragione_sociale LIKE ? OR c.nome_breve LIKE ? OR c.citta LIKE ? OR c.settore LIKE ? OR c.piva LIKE ?)';
    array_push($par, "%$s%", "%$s%", "%$s%", "%$s%", "%$s%");
}
$sql = "SELECT c.*,
  (SELECT nome FROM contatti WHERE cliente_id=c.id ORDER BY principale DESC, id LIMIT 1) AS contatto,
  (SELECT COUNT(*) FROM progetti WHERE cliente_id=c.id AND stato IN ('da_iniziare','in_corso','in_pausa')) AS progetti_attivi,
  (SELECT COALESCE(SUM(importo),0) FROM contratti WHERE cliente_id=c.id AND stato='attivo' AND periodicita='mensile') AS mrr,
  (SELECT MAX(created_at) FROM attivita WHERE cliente_id=c.id) AS ultima
  FROM clienti c " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
  ORDER BY COALESCE(NULLIF(c.nome_breve,''), c.ragione_sociale)";
$rows = all($sql, $par);

// da incassare per cliente
$aperto = [];
foreach (all("SELECT x.cliente_id, SUM(x.totale - x.pagato) AS res FROM (SELECT " . FATT_COLS . " FROM fatture f WHERE f.annullata=0) x GROUP BY x.cliente_id") as $r)
    $aperto[$r['cliente_id']] = (float)$r['res'];

$conteggi = [];
foreach (all('SELECT stato, COUNT(*) n FROM clienti GROUP BY stato') as $r) $conteggi[$r['stato']] = $r['n'];

layout_start('Clienti', 'clienti');
page_head('Clienti', '<a class="btn btn-primary" href="' . url('cliente', ['modifica' => 1]) . '">' . icon('plus') . ' Nuovo cliente</a>');
?>
<div class="toolbar">
  <div class="tabs">
    <?php foreach (['attivi' => 'Clienti', 'potenziale' => 'Potenziali', 'ex' => 'Ex', 'tutti' => 'Tutti'] as $k => $l):
      $n = $k === 'attivi' ? ($conteggi['cliente'] ?? 0) : ($k === 'tutti' ? array_sum($conteggi) : ($conteggi[$k] ?? 0)); ?>
      <a class="<?= $stato === $k ? 'on' : '' ?>" href="<?= url('clienti', ['stato' => $k]) ?>"><?= $l ?> <em><?= $n ?></em></a>
    <?php endforeach; ?>
  </div>
  <form class="filter" method="get"><input type="hidden" name="p" value="clienti"><input type="hidden" name="stato" value="<?= h($stato) ?>">
    <input type="search" name="s" value="<?= h($s) ?>" placeholder="Nome, città, settore, P.IVA…"></form>
</div>

<?php if (!$rows): ?>
  <?= empty_state($s ? 'Nessun cliente trovato.' : 'Ancora nessun cliente qui.', '<a class="btn" href="' . url('cliente', ['modifica' => 1]) . '">Aggiungi il primo</a>') ?>
<?php else: ?>
<div data-bulk>
<div class="card table-wrap">
<table class="table">
  <thead><tr><?= sel_th() ?><th>Cliente</th><th class="hide-sm">Referente</th><th class="hide-sm">Progetti</th><th class="num">Canone/mese</th><th class="num">Da incassare</th><th class="hide-sm">Ultima attività</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr class="row-link" data-href="<?= url('cliente', ['id' => $c['id']]) ?>">
      <?= sel_td((int)$c['id']) ?>
      <td>
        <a class="client-link strong" href="<?= url('cliente', ['id' => $c['id']]) ?>"><?php if ($c['colore']): ?><i class="dot" style="background:<?= h($c['colore']) ?>"></i><?php endif; ?><?= h($c['nome_breve'] ?: $c['ragione_sociale']) ?></a>
        <div class="muted small"><?= h(implode(' · ', array_filter([$c['settore'], $c['citta']]))) ?><?php if ($stato === 'tutti'): ?> <?= badge($c['stato'], STATI_CLIENTE[$c['stato']]) ?><?php endif; ?></div>
      </td>
      <td class="hide-sm"><?= h($c['contatto']) ?></td>
      <td class="hide-sm"><?= $c['progetti_attivi'] ?: '<span class="muted">—</span>' ?></td>
      <td class="num"><?= $c['mrr'] > 0 ? money($c['mrr'], false) : '<span class="muted">—</span>' ?></td>
      <td class="num"><?= ($aperto[$c['id']] ?? 0) > 0.009 ? '<b>' . money($aperto[$c['id']]) . '</b>' : '<span class="muted">—</span>' ?></td>
      <td class="hide-sm muted small"><?= $c['ultima'] ? rel_days($c['ultima']) : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= bulk_bar('clienti', [
  'ex' => ['Segna come ex clienti', 'Segnare come ex clienti gli elementi selezionati ({n})? I loro dati restano.'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Si cancellano anche preventivi, fatture, contratti, progetti, task e file di questi clienti. Non si può annullare.', 'danger'],
]) ?>
</div>
<?php endif;
layout_end();
