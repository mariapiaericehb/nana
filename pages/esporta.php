<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$cosa = get('cosa');
function csv_out(string $name, array $head, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // così Excel legge gli accenti
    fputcsv($o, $head, ';');
    foreach ($rows as $r) fputcsv($o, $r, ';');
    exit;
}
$n = fn($v) => number_format((float)$v, 2, ',', '');
if ($cosa === 'fatture') {
    $anno = (int)get('anno', date('Y'));
    $rows = [];
    foreach (all('SELECT ' . FATT_COLS . ', c.ragione_sociale, c.piva, c.codice_fiscale FROM fatture f JOIN clienti c ON c.id=f.cliente_id WHERE YEAR(f.data)=? ORDER BY f.data, f.id', [$anno]) as $f) {
        [, $st] = fatt_stato($f);
        $ult = val('SELECT MAX(data) FROM pagamenti WHERE fattura_id=?', [$f['id']]);
        $rows[] = [$f['numero'], d($f['data']), $f['ragione_sociale'], $f['piva'], $f['codice_fiscale'], $f['descrizione'], $n($f['imponibile']), $n($f['iva_percentuale']), $n($f['iva']), $n($f['ritenuta']), $n($f['totale']), $n($f['pagato']), $ult ? d($ult) : '', d($f['scadenza']), $st];
    }
    csv_out("fatture-$anno.csv", ['Numero', 'Data', 'Cliente', 'P.IVA', 'Cod. fiscale', 'Descrizione', 'Imponibile', 'IVA %', 'IVA', 'Ritenuta', 'Totale', 'Incassato', 'Ultimo incasso', 'Scadenza', 'Stato'], $rows);
}
if ($cosa === 'clienti') {
    $rows = [];
    foreach (all('SELECT c.*, (SELECT nome FROM contatti WHERE cliente_id=c.id ORDER BY principale DESC LIMIT 1) ref FROM clienti c ORDER BY ragione_sociale') as $c)
        $rows[] = [$c['ragione_sociale'], $c['nome_breve'], STATI_CLIENTE[$c['stato']], $c['settore'], $c['ref'], $c['email'], $c['telefono'], $c['piva'], $c['codice_fiscale'], $c['codice_sdi'], $c['pec'], $c['indirizzo'], $c['cap'], $c['citta'], $c['provincia'], $c['sito']];
    csv_out('clienti.csv', ['Ragione sociale', 'Nome breve', 'Stato', 'Settore', 'Referente', 'Email', 'Telefono', 'P.IVA', 'Cod. fiscale', 'SDI', 'PEC', 'Indirizzo', 'CAP', 'Città', 'Prov.', 'Sito'], $rows);
}
if ($cosa === 'cliente') {
    // tutti i dati di un cliente (utile se chiede quali dati conservi su di lui)
    $id = (int)get('id');
    $c = one('SELECT * FROM clienti WHERE id=?', [$id]);
    if (!$c) redirect(url('clienti'));
    $out = ['esportato_il' => date('c'), 'cliente' => $c,
        'referenti' => all('SELECT * FROM contatti WHERE cliente_id=?', [$id]),
        'note_e_storico' => all('SELECT tipo, testo, created_at FROM attivita WHERE cliente_id=? ORDER BY created_at', [$id]),
        'trattative' => all('SELECT * FROM trattative WHERE cliente_id=?', [$id]),
        'preventivi' => array_map(fn($p) => $p + ['voci' => all('SELECT descrizione, quantita, prezzo FROM preventivo_righe WHERE preventivo_id=? ORDER BY ordine', [$p['id']])], all('SELECT * FROM preventivi WHERE cliente_id=?', [$id])),
        'contratti' => all('SELECT * FROM contratti WHERE cliente_id=?', [$id]),
        'fatture' => array_map(fn($f) => $f + ['pagamenti' => all('SELECT data, importo, metodo, note FROM pagamenti WHERE fattura_id=?', [$f['id']])], all('SELECT * FROM fatture WHERE cliente_id=?', [$id])),
        'progetti' => all('SELECT * FROM progetti WHERE cliente_id=?', [$id]),
        'task' => all('SELECT * FROM task WHERE cliente_id=?', [$id]),
        'appuntamenti' => all('SELECT * FROM eventi WHERE cliente_id=?', [$id]),
        'file' => array_column(all("SELECT nome FROM allegati WHERE entita='cliente' AND entita_id=?", [$id]), 'nome'),
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="dati-' . preg_replace('/[^a-z0-9]+/i', '-', $c['nome_breve'] ?: $c['ragione_sociale']) . '.json"');
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($cosa === 'backup') {
    // il backup si scarica solo subito dopo aver confermato la password in Impostazioni
    if (time() - (int)($_SESSION['backup_ok'] ?? 0) > 60) { flash('Per scaricare il backup conferma la password.', 'err'); redirect(url('impostazioni') . '#sicurezza'); }
    unset($_SESSION['backup_ok']);
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="nana-backup-' . date('Y-m-d') . '.sql"');
    echo "-- Backup Nana del " . date('d/m/Y H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $pdo = db();
    // solo le tabelle del proprio spazio (lo spazio principale include anche quelle comuni)
    $tabelle = array_map(fn($t) => spazio() . $t, TABELLE_SPAZIO);
    if (spazio() === '') $tabelle = array_merge($tabelle, ['utenti', 'accessi', 'login_tentativi']);
    foreach (array_intersect($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), $tabelle) as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        echo "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
        $st = $pdo->query("SELECT * FROM `$t`");
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r);
            echo "INSERT INTO `$t` (`" . implode('`,`', array_keys($r)) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}
redirect(url('impostazioni'));
