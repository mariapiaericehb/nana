<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$chi = get('chi');
$cliente = (int)$chi;
$extra = $chi === 'personale' ? ' AND t.personale=1' : ($chi === 'lavoro' ? ' AND t.personale=0' : ($cliente ? ' AND t.cliente_id=' . $cliente : ''));
$base = "SELECT t.*, p.titolo AS progetto, " . C_COLS . " FROM task t LEFT JOIN progetti p ON p.id=t.progetto_id LEFT JOIN clienti c ON c.id=t.cliente_id";
$aperti = all("$base WHERE t.stato<>'fatto' $extra ORDER BY t.scadenza IS NULL, t.scadenza, t.ora IS NULL, t.ora, FIELD(t.priorita,'alta','media','bassa'), t.ordine");
$fatti = all("$base WHERE t.stato='fatto' AND t.completato_il > NOW() - INTERVAL 7 DAY $extra ORDER BY t.completato_il DESC");

$oggi = date('Y-m-d');
$fine_sett = date('Y-m-d', strtotime('sunday this week'));
$gruppi = ['Scaduti' => [], 'Oggi' => [], 'Questa settimana' => [], 'Più avanti' => [], 'Senza data' => []];
foreach ($aperti as $t) {
    $s = $t['scadenza'];
    if (!$s) $gruppi['Senza data'][] = $t;
    elseif ($s < $oggi) $gruppi['Scaduti'][] = $t;
    elseif ($s === $oggi) $gruppi['Oggi'][] = $t;
    elseif ($s <= $fine_sett) $gruppi['Questa settimana'][] = $t;
    else $gruppi['Più avanti'][] = $t;
}
$progetti_opt = array_map(fn($r) => ['id' => $r['id'], 'label' => ($r['cn'] ? $r['cn'] . ' · ' : '') . $r['titolo']],
    all("SELECT p.id, p.titolo, COALESCE(NULLIF(c.nome_breve,''), c.ragione_sociale) cn FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE p.stato NOT IN ('completato','annullato') OR p.id IN (SELECT progetto_id FROM task WHERE stato<>'fatto') ORDER BY cn, p.titolo"));
$show_client = !$cliente;

layout_start('Task', 'task');
page_head('Task', sel_toggle('più task'), 'Tutto quello che devi fare, in ordine di scadenza.');
?>
<div data-bulk>
<form method="post" action="<?= url('azioni') ?>" class="quick-add card">
  <?= csrf() ?><input type="hidden" name="azione" value="task_rapido">
  <input name="titolo" placeholder="Cosa devi fare?" required>
  <select name="cliente_id" class="hide-sm"><?= task_chi_options($cliente ?: null, $chi === 'personale', 'Lavoro') ?></select>
  <input type="date" name="scadenza" value="<?= $oggi ?>" title="Scadenza">
  <select name="priorita"><?= options(PRIORITA, 'media') ?></select>
  <button class="btn btn-sm btn-primary"><?= icon('plus') ?><span class="hide-sm">Aggiungi</span></button>
</form>
<div class="toolbar">
  <form method="get" class="filter"><input type="hidden" name="p" value="task">
    <select name="chi" onchange="this.form.submit()"><option value="">Tutto</option><option value="personale" <?= $chi === 'personale' ? 'selected' : '' ?>>★ Solo personali</option><option value="lavoro" <?= $chi === 'lavoro' ? 'selected' : '' ?>>Solo lavoro</option><optgroup label="Un cliente"><?= str_replace('<option value=""></option>', '', clienti_options($cliente ?: null, false)) ?></optgroup></select></form>
</div>
<?php $quick = null;
foreach ($gruppi as $nome => $list): if (!$list) continue; ?>
  <section class="card task-group <?= $nome === 'Scaduti' ? 'is-late-group' : '' ?>">
    <h2><?= $nome ?> <em><?= count($list) ?></em></h2>
    <?php $task_list = $list; require APP_ROOT . '/pages/_task_list.php'; ?>
  </section>
<?php endforeach; ?>
<?php if (!$aperti): ?><?= empty_state('Niente da fare. Goditela.') ?><?php endif; ?>
<?php if ($fatti): ?>
  <details class="card task-group"><summary><h2>Fatti negli ultimi 7 giorni <em><?= count($fatti) ?></em></h2></summary>
    <?php $task_list = $fatti; require APP_ROOT . '/pages/_task_list.php'; ?>
  </details>
<?php endif; ?>
<?= bulk_bar('task', [
  'fatto' => ['Segna come fatti'],
  'sposta' => ['Sposta', '', '', '<input type="date" name="nuova_data" class="bulk-date" aria-label="Nuova data">'],
  'elimina' => ['Elimina', 'Eliminare gli elementi selezionati ({n})? Non si può annullare.', 'danger'],
]) ?>
</div>
<?php if (get('apri')): ?><script>document.addEventListener('DOMContentLoaded',()=>{const d=document.querySelector('[data-task="<?= (int)get('apri') ?>"]');if(d){d.open=true;d.scrollIntoView({block:'center'})}})</script><?php endif; ?>
<?php layout_end();
