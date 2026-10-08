<?php
// Calendario da collegare a Google Calendar / iPhone (sola lettura, protetto da chiave segreta)
require __DIR__ . '/inc/bootstrap.php';
$sp = (string)($_GET['s'] ?? '');
if (!preg_match('/^(u\d+_)?$/', $sp) || ($sp !== '' && !q_raw('SELECT 1 FROM utenti WHERE spazio=? AND attivo=1', [$sp])->fetchColumn())) { http_response_code(403); exit('Accesso negato'); }
set_spazio($sp);
$tok = setting('ical_token');
if (setting('ical_attivo') !== '1' || strlen($tok) < 32 || !hash_equals($tok, (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('Accesso negato'); }

function ics_esc(string $s): string { return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\;", "\\,", "\\n", "\\n"], $s); }
function ics_fold(string $line): string { $out = ''; while (strlen($line) > 74) { $cut = 74; while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; $out .= substr($line, 0, $cut) . "\r\n "; $line = substr($line, $cut); } return $out . $line; }
$base = (($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/';
$ev = [];
$add = function (string $uid, string $date, string $title, string $link, ?string $start = null, ?string $end = null, string $desc = '') use (&$ev, $base) {
    $ev[] = compact('uid', 'date', 'title', 'link', 'start', 'end', 'desc') + ['url' => $base . $link];
};
foreach (all("SELECT e.*, " . C_COLS . " FROM eventi e LEFT JOIN clienti c ON c.id=e.cliente_id WHERE e.data >= CURDATE() - INTERVAL 60 DAY OR e.ripeti<>'no'") as $e) {
    $add("ev{$e['id']}", $e['data'], $e['titolo'] . (cliente_nome($e) ? ' · ' . cliente_nome($e) : ''), url('calendario', ['evento' => $e['id']]), $e['ora_inizio'], $e['ora_fine'], trim(($e['luogo'] ?? '') . "\n" . ($e['note'] ?? '')));
    if ($e['ripeti'] !== 'no') $ev[count($ev) - 1]['rrule'] = 'FREQ=' . ['settimana' => 'WEEKLY', 'mese' => 'MONTHLY', 'anno' => 'YEARLY'][$e['ripeti']] . ($e['ripeti_fino'] ? ';UNTIL=' . str_replace('-', '', $e['ripeti_fino']) . 'T235959Z' : '');
}
foreach (all("SELECT t.*, " . C_COLS . " FROM task t LEFT JOIN clienti c ON c.id=t.cliente_id WHERE t.stato<>'fatto' AND t.scadenza IS NOT NULL") as $t)
    $add("task{$t['id']}", $t['scadenza'], '☐ ' . $t['titolo'] . (cliente_nome($t) ? ' · ' . cliente_nome($t) : ''), url('task', ['apri' => $t['id']]), $t['ora'], null, (string)$t['descrizione']);
foreach (all('SELECT x.*, ' . C_COLS . ' FROM (SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.annullata=0 AND f.scadenza IS NOT NULL) x JOIN clienti c ON c.id=x.cliente_id WHERE x.totale - x.pagato > 0.009') as $f)
    $add("fatt{$f['id']}", $f['scadenza'], '€ Scade fattura ' . $f['numero'] . ' · ' . cliente_nome($f), url('fattura', ['id' => $f['id']]));
foreach (all("SELECT p.*, " . C_COLS . " FROM progetti p LEFT JOIN clienti c ON c.id=p.cliente_id WHERE p.stato IN ('da_iniziare','in_corso','in_pausa') AND p.scadenza IS NOT NULL") as $p)
    $add("prog{$p['id']}", $p['scadenza'], 'Consegna: ' . $p['titolo'], url('progetto', ['id' => $p['id']]));
foreach (all("SELECT k.*, " . C_COLS . " FROM contratti k JOIN clienti c ON c.id=k.cliente_id WHERE k.stato='attivo'") as $k) {
    if ($f = contr_scadenza($k)) $add("ctr{$k['id']}", $f, ($k['rinnovo_automatico'] ? 'Rinnovo: ' : 'Scade gestione: ') . $k['titolo'] . ' · ' . cliente_nome($k), url('contratto', ['id' => $k['id']]));
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="hypebang.ics"');
$tz = date_default_timezone_get();
$lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Nana//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
    'X-WR-CALNAME:' . ics_esc(app_nome()), 'X-WR-TIMEZONE:' . $tz, 'REFRESH-INTERVAL;VALUE=DURATION:PT2H', 'X-PUBLISHED-TTL:PT2H'];
$host = $_SERVER['HTTP_HOST'] ?? 'hypebang';
foreach ($ev as $e) {
    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $e['uid'] . '@' . $host;
    $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
    $d = str_replace('-', '', $e['date']);
    if ($e['start']) {
        $s = str_replace(':', '', substr($e['start'], 0, 5)) . '00';
        $end = $e['end'] ? str_replace(':', '', substr($e['end'], 0, 5)) . '00' : date('His', strtotime($e['start'] . ' +1 hour'));
        $lines[] = "DTSTART;TZID=$tz:{$d}T$s";
        $lines[] = "DTEND;TZID=$tz:{$d}T$end";
    } else {
        $lines[] = "DTSTART;VALUE=DATE:$d";
        $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($e['date'] . ' +1 day'));
    }
    if (!empty($e['rrule'])) $lines[] = 'RRULE:' . $e['rrule'];
    $lines[] = 'SUMMARY:' . ics_esc($e['title']);
    if ($e['desc']) $lines[] = 'DESCRIPTION:' . ics_esc($e['desc']);
    $lines[] = 'URL:' . $e['url'];
    $lines[] = 'END:VEVENT';
}
$lines[] = 'END:VCALENDAR';
echo implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
