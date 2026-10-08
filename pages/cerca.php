<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$s = get('q');
$res = [];
if (mb_strlen($s) >= 2) {
    $l = "%$s%";
    foreach (all('SELECT id, ragione_sociale, nome_breve, citta, stato FROM clienti WHERE ragione_sociale LIKE ? OR nome_breve LIKE ? OR piva LIKE ? OR email LIKE ? OR note LIKE ? LIMIT 20', [$l, $l, $l, $l, $l]) as $r)
        $res['Clienti'][] = [url('cliente', ['id' => $r['id']]), $r['nome_breve'] ?: $r['ragione_sociale'], trim(STATI_CLIENTE[$r['stato']] . ' · ' . $r['citta'], ' ·')];
    foreach (all('SELECT k.id, k.nome, k.ruolo, k.cliente_id, ' . C_COLS . ' FROM contatti k JOIN clienti c ON c.id=k.cliente_id WHERE k.nome LIKE ? OR k.email LIKE ? OR k.telefono LIKE ? LIMIT 20', [$l, $l, $l]) as $r)
        $res['Referenti'][] = [url('cliente', ['id' => $r['cliente_id']]), $r['nome'], trim(($r['ruolo'] ?? '') . ' · ' . cliente_nome($r), ' ·')];
    foreach (all('SELECT p.id, p.numero, p.oggetto, ' . C_COLS . ' FROM preventivi p JOIN clienti c ON c.id=p.cliente_id WHERE p.numero LIKE ? OR p.oggetto LIKE ? OR p.id IN (SELECT preventivo_id FROM preventivo_righe WHERE descrizione LIKE ?) LIMIT 20', [$l, $l, $l]) as $r)
        $res['Preventivi'][] = [url('preventivo', ['id' => $r['id']]), $r['numero'] . ' · ' . $r['oggetto'], cliente_nome($r)];
    foreach (all('SELECT f.id, f.numero, f.descrizione, f.data, ' . C_COLS . ' FROM fatture f JOIN clienti c ON c.id=f.cliente_id WHERE f.numero = ? OR f.descrizione LIKE ? LIMIT 20', [$s, $l]) as $r)
        $res['Fatture'][] = [url('fattura', ['id' => $r['id']]), 'Fattura ' . $r['numero'] . ' del ' . d($r['data']), trim(cliente_nome($r) . ' · ' . $r['descrizione'], ' ·')];
    foreach (all('SELECT p.id, p.titolo, ' . C_COLS . ' FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE p.titolo LIKE ? OR p.descrizione LIKE ? LIMIT 20', [$l, $l]) as $r)
        $res['Progetti'][] = [url('progetto', ['id' => $r['id']]), $r['titolo'], cliente_nome($r)];
    foreach (all('SELECT t.id, t.titolo, t.stato, ' . C_COLS . ' FROM task t LEFT JOIN clienti c ON c.id=t.cliente_id WHERE t.titolo LIKE ? OR t.descrizione LIKE ? ORDER BY t.stato=\'fatto\' LIMIT 20', [$l, $l]) as $r)
        $res['Task'][] = [url('task', ['apri' => $r['id']]), $r['titolo'], trim(cliente_nome($r) . ($r['stato'] === 'fatto' ? ' · fatto' : ''), ' ·')];
    foreach (all('SELECT a.testo, a.created_at, a.cliente_id, ' . C_COLS . ' FROM attivita a JOIN clienti c ON c.id=a.cliente_id WHERE a.tipo<>\'sistema\' AND a.testo LIKE ? ORDER BY a.created_at DESC LIMIT 20', [$l]) as $r)
        $res['Note'][] = [url('cliente', ['id' => $r['cliente_id']]), mb_strimwidth($r['testo'], 0, 120, '…'), cliente_nome($r) . ' · ' . d($r['created_at'])];
}
layout_start('Cerca', '');
page_head('Cerca');
?>
<form method="get" class="card search-big"><input type="hidden" name="p" value="cerca"><?= icon('search') ?><input type="search" name="q" value="<?= h($s) ?>" placeholder="Cliente, referente, numero fattura, task, nota…" autofocus></form>
<?php if ($s !== '' && !$res): ?><?= empty_state('Nessun risultato per "' . $s . '".') ?><?php endif; ?>
<?php foreach ($res as $gruppo => $list): ?>
  <section class="card"><h2><?= $gruppo ?> <em><?= count($list) ?></em></h2>
    <?php foreach ($list as [$link, $title, $sub]): ?><a class="mini-item" href="<?= h($link) ?>"><span><b><?= h($title) ?></b> <span class="muted small"><?= h($sub) ?></span></span></a><?php endforeach; ?>
  </section>
<?php endforeach;
layout_end();
