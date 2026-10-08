<?php
// Promemoria giornaliero via email. Su SiteGround: Site Tools → Devs → Cron Jobs
// comando:  php /home/customer/www/TUODOMINIO/public_html/crm/cron.php   (ogni giorno alle 7:30)
// Si attiva mettendo 'mail_enabled' => true in config.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/inc/bootstrap.php';
global $CONFIG;
if (empty($CONFIG['mail_enabled'])) { echo "Promemoria disattivato (mail_enabled = false)\n"; exit; }
foreach (q_raw('SELECT * FROM utenti WHERE attivo=1 ORDER BY id')->fetchAll() as $u) {
    set_spazio((string)$u['spazio']);
    $righe = [];
    $t = all("SELECT t.titolo, t.scadenza, " . C_COLS . " FROM task t LEFT JOIN clienti c ON c.id=t.cliente_id WHERE t.stato<>'fatto' AND t.scadenza <= CURDATE() ORDER BY t.scadenza");
    if ($t) { $righe[] = "DA FARE OGGI (" . count($t) . ")"; foreach ($t as $x) $righe[] = "- " . $x['titolo'] . (cliente_nome($x) ? " · " . cliente_nome($x) : '') . ($x['scadenza'] < date('Y-m-d') ? " (scaduto " . rel_days($x['scadenza']) . ")" : ''); $righe[] = ''; }
    $e = eventi_range(date('Y-m-d'), date('Y-m-d'));
    if ($e) { $righe[] = "APPUNTAMENTI DI OGGI"; foreach ($e as $x) $righe[] = "- " . t($x['ora_inizio']) . " " . $x['titolo'] . (cliente_nome($x) ? " · " . cliente_nome($x) : ''); $righe[] = ''; }
    $f = all('SELECT x.*, ' . C_COLS . ' FROM (SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.annullata=0) x JOIN clienti c ON c.id=x.cliente_id WHERE x.totale - x.pagato > 0.009 AND x.scadenza < CURDATE()');
    if ($f) { $righe[] = "FATTURE SCADUTE"; foreach ($f as $x) $righe[] = "- " . cliente_nome($x) . ": " . money($x['totale'] - $x['pagato']) . " (fattura " . $x['numero'] . ", scaduta " . rel_days($x['scadenza']) . ")"; $righe[] = ''; }
    foreach (all("SELECT k.*, " . C_COLS . " FROM contratti k JOIN clienti c ON c.id=k.cliente_id WHERE k.stato='attivo'") as $k) {
        $fine = contr_scadenza($k);
        if ($fine && days_until($fine) === (int)$k['preavviso_giorni']) $righe[] = "! Gestione " . $k['titolo'] . " (" . cliente_nome($k) . ") " . ($k['rinnovo_automatico'] ? 'si rinnova' : 'scade') . " il " . d($fine) . ": ultimo giorno per decidere.";
        $nf = contr_prossima_fattura($k);
        if ($nf === date('Y-m-d')) $righe[] = "€ Oggi va fatta la fattura: " . $k['titolo'] . " (" . cliente_nome($k) . "), " . money($k['importo']);
    }
    if (!$righe) { echo "{$u['email']}: niente da segnalare\n"; continue; }
    $nome = app_nome();
    $body = "Ecco la tua giornata.\n\n" . implode("\n", $righe) . "\n";
    $headers = "Content-Type: text/plain; charset=utf-8\r\nFrom: $nome <" . $u['email'] . ">";
    mail($u['email'], "=?UTF-8?B?" . base64_encode("$nome · la tua giornata, " . d(date('Y-m-d'))) . "?=", $body, $headers);
    echo "Inviato a {$u['email']}\n";
}
