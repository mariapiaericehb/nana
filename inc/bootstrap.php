<?php
// Nana - avvio, database e funzioni comuni
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '2.0');
// indirizzo di un file statico con la data di modifica: dopo un aggiornamento il browser scarica la versione nuova
function asset(string $f): string { $p = APP_ROOT . '/' . $f; return $f . '?v=' . (is_file($p) ? filemtime($p) : APP_VERSION); }

if (!is_file(APP_ROOT . '/config.php')) {
    header('Location: install/');
    exit;
}
$CONFIG = require APP_ROOT . '/config.php';
date_default_timezone_set($CONFIG['timezone'] ?? 'Europe/Rome');
if (!empty($CONFIG['debug'])) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); }

// ---------- HTTPS e intestazioni di sicurezza ----------
$is_local = in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1', '::1'], true);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (PHP_SAPI !== 'cli') {
    if (!$secure && !$is_local && ($CONFIG['force_https'] ?? true)) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
    if ($secure) header('Strict-Transport-Security: max-age=31536000');
    // le pagine con dati dei clienti non restano nella cache del browser
    header('Cache-Control: no-store, private');
    header('X-Accel-Expires: 0'); // niente cache sul server (nginx di SiteGround)
    header('Pragma: no-cache');
    header('Expires: 0');
}

// ---------- Sessione ----------
const SESSIONE_INATTIVA = 4 * 3600;   // esci dopo 4 ore senza usare la piattaforma
const SESSIONE_MAX = 7 * 24 * 3600;   // e comunque dopo 7 giorni
session_name($secure ? '__Host-hbcrm' : 'hbcrm');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => $secure]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string)SESSIONE_INATTIVA);
if (PHP_SAPI !== 'cli') {
    session_start();
    if (!empty($_SESSION['uid'])) {
        $now = time();
        if ($now - ($_SESSION['last'] ?? $now) > SESSIONE_INATTIVA || $now - ($_SESSION['since'] ?? $now) > SESSIONE_MAX) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['scaduta'] = 1;
        } else {
            $_SESSION['last'] = $now;
        }
    }
}

// ---------- Database ----------
function db(): PDO {
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $CONFIG['db_host'], $CONFIG['db_name']);
        $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
// ---------- Spazi separati ----------
// Ogni utente ha le sue tabelle: lo spazio principale usa i nomi normali (clienti, fatture…),
// gli altri lo stesso nome con un prefisso (u2_clienti, u2_fatture…). Le tabelle utenti/accessi sono comuni.
const TABELLE_SPAZIO = ['impostazioni','clienti','contatti','trattative','preventivi','preventivo_righe','contratti','fatture','pagamenti','progetti','task','eventi','attivita','allegati'];
$GLOBALS['SPAZIO'] = '';
function spazio(): string { return $GLOBALS['SPAZIO']; }
function set_spazio(string $p): void {
    if (!preg_match('/^(u\d+_)?$/', $p)) throw new RuntimeException('Spazio non valido');
    $GLOBALS['SPAZIO'] = $p;
    setting_reset();
}
function tsql(string $sql): string {
    $p = spazio();
    if ($p === '') return $sql;
    static $re = null;
    $re ??= '/\b(FROM|JOIN|INTO|UPDATE|TABLE|REFERENCES|EXISTS)(\s+)(`?)(' . implode('|', TABELLE_SPAZIO) . ')\3(?![\w])/i';
    return preg_replace_callback($re, fn($m) => $m[1] . $m[2] . $m[3] . $p . $m[4] . $m[3], $sql);
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare(tsql($sql)); $s->execute($p); return $s; }
// query sulle tabelle comuni o di sistema, senza prefisso
function q_raw(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function insert(string $table, array $data): int {
    $cols = array_keys($data);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}
function update(string $table, int $id, array $data): void {
    if (!$data) return;
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
    q("UPDATE `$table` SET $set WHERE id=?", [...array_values($data), $id]);
}
function delete_row(string $table, int $id): void { q("DELETE FROM `$table` WHERE id=?", [$id]); }

// ---------- Colori ----------
const COLORE_DEFAULT = '#dc564a';
function colore(): string {
    $c = setting('colore', COLORE_DEFAULT);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : COLORE_DEFAULT;
}
function tema(): string { return setting('tema') === 'verde' ? 'verde' : 'classico'; }
function tema_class(): string { return tema() === 'verde' ? ' class="tema-verde"' : ''; }
function _lum(array $rgb): float {
    $f = fn($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    return 0.2126 * $f($rgb[0]) + 0.7152 * $f($rgb[1]) + 0.0722 * $f($rgb[2]);
}
// variabili CSS: colore principale, testo leggibile sopra, e una versione scura per i testi su bianco
function theme_css(): string {
    $hex = colore();
    $rgb = array_map('hexdec', str_split(substr($hex, 1), 2));
    $on = _lum($rgb) > 0.4 ? '#16140f' : '#ffffff';
    $t = $rgb;
    for ($i = 0; $i < 40 && (1.05) / (_lum($t) + 0.05) < 4.5; $i++) $t = array_map(fn($v) => (int)round($v * 0.92), $t);
    $text = sprintf('#%02x%02x%02x', ...$t);
    return ":root{--accent:$hex;--on-accent:$on;--accent-text:$text}";
}

// ---------- Impostazioni ----------
function setting(string $k, string $default = ''): string {
    if (!isset($GLOBALS['_SETTINGS'])) {
        $GLOBALS['_SETTINGS'] = [];
        foreach (all('SELECT chiave, valore FROM impostazioni') as $r) $GLOBALS['_SETTINGS'][$r['chiave']] = (string)$r['valore'];
    }
    $c = $GLOBALS['_SETTINGS'];
    return ($c[$k] ?? '') !== '' ? $c[$k] : $default;
}
function setting_reset(): void { unset($GLOBALS['_SETTINGS']); }
function set_setting(string $k, ?string $v): void {
    q('INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)', [$k, $v]);
    setting_reset();
}

// ---------- Output ----------
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function money($n, bool $cents = true): string {
    return '€ ' . number_format((float)$n, $cents ? 2 : 0, ',', '.');
}
function num_it($n): string { $s = number_format((float)$n, 2, ',', ''); return preg_replace('/,00$/', '', $s); }
const MESI = ['', 'gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
const GIORNI = ['dom','lun','mar','mer','gio','ven','sab'];
const GIORNI_LUNGHI = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
function d($date, bool $long = false): string {
    if (!$date) return '';
    $t = strtotime((string)$date);
    if (!$t) return '';
    return $long ? (int)date('j', $t) . ' ' . MESI[(int)date('n', $t)] . ' ' . date('Y', $t) : date('d/m/Y', $t);
}
function d_short($date): string {
    if (!$date) return '';
    $t = strtotime((string)$date);
    return (int)date('j', $t) . ' ' . substr(MESI[(int)date('n', $t)], 0, 3);
}
function t($time): string { return $time ? substr((string)$time, 0, 5) : ''; }
function days_until($date): ?int {
    if (!$date) return null;
    $a = new DateTime(date('Y-m-d')); $b = new DateTime(substr((string)$date, 0, 10));
    return (int)$a->diff($b)->format('%r%a');
}
function rel_days($date): string {
    $n = days_until($date);
    if ($n === null) return '';
    if ($n === 0) return 'oggi';
    if ($n === 1) return 'domani';
    if ($n === -1) return 'ieri';
    return $n > 0 ? "tra $n giorni" : (-$n) . ' giorni fa';
}
function due_class($date, bool $done = false): string {
    if ($done || !$date) return '';
    $n = days_until($date);
    if ($n < 0) return 'is-late';
    if ($n <= 2) return 'is-soon';
    return '';
}

// ---------- Etichette ----------
const STATI_CLIENTE = ['potenziale' => 'Potenziale', 'cliente' => 'Cliente', 'ex' => 'Ex cliente'];
const FASI = ['nuova' => 'Nuovo contatto', 'contattato' => 'Contattato', 'preventivo' => 'Preventivo inviato', 'negoziazione' => 'Negoziazione', 'vinta' => 'Vinta', 'persa' => 'Persa'];
const FASI_PROB = ['nuova' => 10, 'contattato' => 25, 'preventivo' => 50, 'negoziazione' => 75, 'vinta' => 100, 'persa' => 0];
const STATI_PREV = ['bozza' => 'Bozza', 'inviato' => 'Inviato', 'accettato' => 'Accettato', 'rifiutato' => 'Rifiutato'];
const PERIODICITA = ['una_tantum' => 'Una tantum', 'mensile' => 'Mensile', 'trimestrale' => 'Trimestrale', 'semestrale' => 'Semestrale', 'annuale' => 'Annuale'];
const PERIODO_MESI = ['una_tantum' => 0, 'mensile' => 1, 'trimestrale' => 3, 'semestrale' => 6, 'annuale' => 12];
const STATI_CONTR = ['attivo' => 'Attiva', 'concluso' => 'Conclusa', 'disdetto' => 'Disdetta'];
const STATI_PROG = ['da_iniziare' => 'Da iniziare', 'in_corso' => 'In corso', 'in_pausa' => 'In pausa', 'completato' => 'Completato', 'annullato' => 'Annullato'];
const STATI_TASK = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'in_attesa' => 'In attesa', 'fatto' => 'Fatto'];
const PRIORITA = ['bassa' => 'Bassa', 'media' => 'Media', 'alta' => 'Alta'];
const TIPI_EVENTO = ['appuntamento' => 'Appuntamento di lavoro', 'call' => 'Call', 'promemoria' => 'Promemoria', 'personale' => 'Personale', 'altro' => 'Altro'];
const RIPETI = ['no' => 'Non si ripete', 'settimana' => 'Ogni settimana', 'mese' => 'Ogni mese', 'anno' => 'Ogni anno'];
const TIPI_NOTA = ['nota' => 'Nota', 'chiamata' => 'Chiamata', 'email' => 'Email', 'incontro' => 'Incontro'];

function pl(int $n, string $uno, string $tanti): string { return $n . ' ' . ($n === 1 ? $uno : $tanti); }
function badge(string $key, string $label): string { return '<span class="badge b-' . h($key) . '">' . h($label) . '</span>'; }

// ---------- Richieste ----------
function post(string $k, $default = '') { $v = $_POST[$k] ?? $default; return is_string($v) ? trim($v) : $v; }
function get(string $k, $default = '') { $v = $_GET[$k] ?? $default; return is_string($v) ? trim($v) : $v; }
function nul($v) { return ($v === '' || $v === null) ? null : $v; }
function dec($v): float {
    $v = trim((string)$v);
    if ($v === '') return 0.0;
    $v = str_replace([' ', '€'], '', $v);
    if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
    elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v); // 1.200 = milleduecento
    return round((float)$v, 2);
}
function date_or_null($v): ?string { $v = trim((string)$v); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null; }
function time_or_null($v): ?string { $v = trim((string)$v); return preg_match('/^\d{2}:\d{2}/', $v) ? substr($v, 0, 5) : null; }
function id_or_null($v): ?int { $v = (int)$v; return $v > 0 ? $v : null; }
function pick($v, array $allowed, string $default): string { return array_key_exists($v, $allowed) ? $v : $default; }

function url(string $p, array $params = []): string {
    $params = ['p' => $p] + $params;
    return 'index.php?' . http_build_query($params);
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function back(string $fallback = 'index.php'): never {
    $r = $_POST['_back'] ?? $_SERVER['HTTP_REFERER'] ?? $fallback;
    // solo link interni
    if (!preg_match('#^(index\.php|/|https?://' . preg_quote($_SERVER['HTTP_HOST'] ?? '', '#') . ')#', $r)) $r = $fallback;
    redirect($r);
}
function flash(?string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

// ---------- Sicurezza ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function check_csrf(): void {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Sessione scaduta: torna indietro e ricarica la pagina.');
    }
}
function user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = false;
    if ($u === false) $u = q_raw('SELECT id, nome, email, spazio, ruolo, cambia_password, attivo FROM utenti WHERE id=?', [$_SESSION['uid']])->fetch() ?: null;
    return $u;
}
function is_admin(): bool { return (user()['ruolo'] ?? '') === 'admin'; }
function require_login(): void {
    $u = user();
    if (!$u || !$u['attivo'] || ($u['spazio'] ?? '') !== spazio()) {
        $_SESSION = [];
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    // spazio incompleto (per esempio creato quando mancava un file): si ripara da solo
    if (spazio() !== '' && empty($_SESSION['spazio_ok'])) { ripara_spazio(spazio(), (string)$u['nome']); $_SESSION['spazio_ok'] = 1; }
    // password temporanea: prima di tutto va cambiata
    if ($u['cambia_password'] && (($_GET['p'] ?? '') !== 'password')) redirect('index.php?p=password');
}

// ---------- Verifica in due passaggi (TOTP, compatibile con Google Authenticator, Authy, 1Password…) ----------
function b32_decode(string $s): string {
    $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $ch) $bits .= str_pad(decbin(strpos($alf, $ch)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function b32_random(int $len = 32): string { $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = ''; for ($i = 0; $i < $len; $i++) $s .= $alf[random_int(0, 31)]; return $s; }
function totp_code(string $secret, int $step): string {
    $h = hash_hmac('sha1', pack('J', $step), b32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24 | ord($h[$o + 1]) << 16 | ord($h[$o + 2]) << 8 | ord($h[$o + 3])) % 1000000;
    return str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}
function totp_check(string $secret, string $code, ?int &$step_used = null): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $t = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) if (hash_equals(totp_code($secret, $t + $i), $code)) { $step_used = $t + $i; return true; }
    return false;
}

function browser_breve(?string $ua): string {
    $ua = (string)$ua;
    $os = preg_match('/iPhone|iPad/', $ua) ? 'iPhone/iPad' : (preg_match('/Android/', $ua) ? 'Android' : (preg_match('/Mac OS/', $ua) ? 'Mac' : (preg_match('/Windows/', $ua) ? 'Windows' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser')));
    return trim("$br $os");
}
function registra_accesso(int $uid, string $esito): void {
    q('INSERT INTO accessi (utente_id, esito, ip, browser) VALUES (?,?,?,?)', [$uid, $esito, $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)]);
    q('DELETE FROM accessi WHERE created_at < NOW() - INTERVAL 90 DAY');
}

// ---------- Storico ----------
function log_act(?int $cliente_id, string $testo, ?string $link = null, string $tipo = 'sistema'): void {
    insert('attivita', ['cliente_id' => $cliente_id, 'tipo' => $tipo, 'testo' => $testo, 'link' => $link]);
}

// ---------- Dati calcolati ----------
function prev_totali(int $id): array {
    $p = one('SELECT iva_percentuale, sconto FROM preventivi WHERE id=?', [$id]);
    $sub = (float)val('SELECT COALESCE(SUM(quantita*prezzo),0) FROM preventivo_righe WHERE preventivo_id=?', [$id]);
    $imp = max(0, $sub - (float)($p['sconto'] ?? 0));
    $iva = round($imp * (float)($p['iva_percentuale'] ?? 0) / 100, 2);
    return ['subtotale' => $sub, 'sconto' => (float)($p['sconto'] ?? 0), 'imponibile' => $imp, 'iva' => $iva, 'totale' => $imp + $iva];
}
// SQL per i totali delle fatture (da usare come colonne)
const FATT_COLS = "f.*,
  ROUND(f.imponibile*f.iva_percentuale/100,2) AS iva,
  ROUND(f.imponibile*f.ritenuta_percentuale/100,2) AS ritenuta,
  ROUND(f.imponibile + f.imponibile*f.iva_percentuale/100 - f.imponibile*f.ritenuta_percentuale/100, 2) AS totale,
  COALESCE((SELECT SUM(importo) FROM pagamenti pg WHERE pg.fattura_id=f.id),0) AS pagato";
function fatt_stato(array $f): array {
    if ($f['annullata']) return ['annullata', 'Annullata'];
    $res = round((float)$f['totale'] - (float)$f['pagato'], 2);
    if ($res <= 0) return ['pagata', 'Pagata'];
    if ($f['scadenza'] && days_until($f['scadenza']) < 0) return ['scaduta', 'Scaduta'];
    if ((float)$f['pagato'] > 0) return ['parziale', 'Pagata in parte'];
    return ['aperta', 'Da incassare'];
}
function contr_scadenza(array $c): ?string {
    // data di fine del contratto; con rinnovo automatico, la prossima data di rinnovo
    if (!$c['data_fine']) return null;
    $fine = $c['data_fine'];
    if ($c['rinnovo_automatico'] && $c['stato'] === 'attivo' && $fine < date('Y-m-d')) {
        $durata = durata_mesi($c['data_inizio'], $c['data_fine']);
        $guard = 0;
        while ($fine < date('Y-m-d') && $guard++ < 600) $fine = sposta_fine($fine, $durata);
    }
    return $fine;
}
// durata in mesi di un periodo (1 gen - 31 dic = 12)
function durata_mesi(string $inizio, string $fine): int {
    $i = new DateTime($inizio); $f = (new DateTime($fine))->modify('+1 day');
    $m = ((int)$f->format('Y') - (int)$i->format('Y')) * 12 + ((int)$f->format('n') - (int)$i->format('n'));
    return $m > 0 ? $m : 12;
}
// sposta la data di fine di N mesi (31 dic + 12 mesi = 31 dic dell'anno dopo)
function sposta_fine(string $fine, int $mesi): string {
    $d = (new DateTime($fine))->modify('+1 day');
    $d->modify('first day of this month')->modify("+$mesi months");
    $giorno = min((int)date('j', strtotime($fine . ' +1 day')), (int)$d->format('t'));
    $d->setDate((int)$d->format('Y'), (int)$d->format('n'), $giorno)->modify('-1 day');
    return $d->format('Y-m-d');
}
function next_numero_preventivo(): string {
    $y = date('Y');
    $last = val("SELECT numero FROM preventivi WHERE numero LIKE ? ORDER BY id DESC LIMIT 1", ["$y-%"]);
    $n = $last ? ((int)substr($last, 5)) + 1 : 1;
    return sprintf('%s-%03d', $y, $n);
}
function next_numero_fattura(): string {
    $y = date('Y');
    $rows = all("SELECT numero FROM fatture WHERE YEAR(data)=?", [$y]);
    $max = 0;
    foreach ($rows as $r) if (preg_match('/(\d+)/', $r['numero'], $m)) $max = max($max, (int)$m[1]);
    return (string)($max + 1);
}

function clienti_options(?int $sel = null, bool $empty = true, string $emptyLabel = '— Nessun cliente —'): string {
    $o = $empty ? '<option value="">' . h($emptyLabel) . '</option>' : '';
    foreach (all("SELECT id, ragione_sociale, nome_breve, stato FROM clienti ORDER BY stato='ex', COALESCE(NULLIF(nome_breve,''), ragione_sociale)") as $c) {
        $n = $c['nome_breve'] ?: $c['ragione_sociale'];
        if ($c['stato'] === 'ex') $n .= ' (ex)';
        if ($c['stato'] === 'potenziale') $n .= ' (potenziale)';
        $o .= '<option value="' . $c['id'] . '"' . ($sel === (int)$c['id'] ? ' selected' : '') . '>' . h($n) . '</option>';
    }
    return $o;
}
// menu "per chi è" dei task: Personale, lavoro senza cliente, oppure un cliente
function task_chi_options(?int $cliente = null, bool $personale = false, string $vuoto = 'Lavoro'): string {
    return '<option value="">' . h($vuoto) . '</option><option value="personale"' . ($personale ? ' selected' : '') . '>★ Personale</option>'
        . '<optgroup label="Clienti">' . clienti_options($personale ? null : $cliente, false) . '</optgroup>';
}
function task_chi(string $v): array { // [cliente_id, personale]
    return $v === 'personale' ? [null, 1] : [id_or_null($v), 0];
}
function options(array $map, $sel): string {
    $o = '';
    foreach ($map as $k => $v) $o .= '<option value="' . h($k) . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . h($v) . '</option>';
    return $o;
}
function cliente_nome(?array $r, string $prefix = 'c_'): string {
    if (!$r || empty($r[$prefix . 'id'])) return '';
    return $r[$prefix . 'nome_breve'] ?: $r[$prefix . 'ragione_sociale'];
}
function cliente_link(?array $r, string $prefix = 'c_'): string {
    $n = cliente_nome($r, $prefix);
    if ($n === '') return '<span class="muted">—</span>';
    $dot = !empty($r[$prefix . 'colore']) ? '<i class="dot" style="background:' . h($r[$prefix . 'colore']) . '"></i>' : '';
    return '<a class="client-link" href="' . url('cliente', ['id' => $r[$prefix . 'id']]) . '">' . $dot . h($n) . '</a>';
}
// colonne cliente da unire nelle query: JOIN clienti c
const C_COLS = "c.id AS c_id, c.ragione_sociale AS c_ragione_sociale, c.nome_breve AS c_nome_breve, c.colore AS c_colore";

// ---------- Appuntamenti (anche quelli che si ripetono) ----------
// Restituisce gli appuntamenti tra $from e $to, ognuno con la data in cui cade ('quando').
// $filtro: 'tutto', 'lavoro', 'personale'
function eventi_range(string $from, string $to, string $filtro = 'tutto'): array {
    $w = $filtro === 'personale' ? " AND e.tipo='personale'" : ($filtro === 'lavoro' ? " AND e.tipo<>'personale'" : '');
    $rows = all('SELECT e.*, ' . C_COLS . " FROM eventi e LEFT JOIN clienti c ON c.id=e.cliente_id
        WHERE ((e.ripeti='no' AND e.data BETWEEN ? AND ?) OR (e.ripeti<>'no' AND e.data <= ? AND (e.ripeti_fino IS NULL OR e.ripeti_fino >= ?))) $w", [$from, $to, $to, $from]);
    $out = [];
    foreach ($rows as $e) {
        if ($e['ripeti'] === 'no') { $out[] = $e + ['quando' => $e['data']]; continue; }
        $start = new DateTime($e['data']);
        $g = (int)$start->format('j'); $m = (int)$start->format('n');
        $fine = min($to, $e['ripeti_fino'] ?: $to);
        $guard = 0;
        for ($i = 0; $guard++ < 1200; $i++) {
            if ($e['ripeti'] === 'settimana') $d = (clone $start)->modify('+' . (7 * $i) . ' days');
            elseif ($e['ripeti'] === 'mese') {
                $d = (clone $start)->modify('first day of this month')->modify("+$i months");
                if ($g > (int)$d->format('t')) continue; // es. il 31 nei mesi più corti
                $d->setDate((int)$d->format('Y'), (int)$d->format('n'), $g);
            } else {
                $y = (int)$start->format('Y') + $i;
                if (!checkdate($m, $g, $y)) { $d = new DateTime("$y-02-28"); } else $d = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $g));
            }
            $ds = $d->format('Y-m-d');
            if ($ds > $fine) break;
            if ($ds >= $from) $out[] = $e + ['quando' => $ds];
        }
    }
    usort($out, fn($a, $b) => [$a['quando'], $a['ora_inizio'] === null, $a['ora_inizio']] <=> [$b['quando'], $b['ora_inizio'] === null, $b['ora_inizio']]);
    return $out;
}
// ---------- Importazione appuntamenti ----------
// Legge righe incollate (anche da Excel): in ogni riga una data e due orari. Restituisce [[data, inizio, fine], ...]
function leggi_righe_date(string $testo): array {
    $mesi = ['gennaio'=>1,'febbraio'=>2,'marzo'=>3,'aprile'=>4,'maggio'=>5,'giugno'=>6,'luglio'=>7,'agosto'=>8,'settembre'=>9,'ottobre'=>10,'novembre'=>11,'dicembre'=>12,
             'gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];
    $out = [];
    foreach (preg_split('/\R/', $testo) as $riga) {
        $r = mb_strtolower(trim($riga));
        if ($r === '') continue;
        $data = null;
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $r, $m)) { [$y, $mo, $g] = [(int)$m[1], (int)$m[2], (int)$m[3]]; }
        elseif (preg_match('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})\b/', $r, $m)) { [$g, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]]; if ($y < 100) $y += 2000; }
        elseif (preg_match('/\b(\d{1,2})\s+(' . implode('|', array_keys($mesi)) . ')[a-z]*\.?\s+(\d{4})/u', $r, $m)) { [$g, $mo, $y] = [(int)$m[1], $mesi[$m[2]], (int)$m[3]]; }
        else continue;
        if (!checkdate($mo, $g, $y)) continue;
        $data = sprintf('%04d-%02d-%02d', $y, $mo, $g);
        $resto = str_replace($m[0], ' ', $r); // gli orari si cercano fuori dalla data
        preg_match_all('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', $resto, $tt, PREG_SET_ORDER);
        $ore = array_map(fn($x) => sprintf('%02d:%02d', $x[1], $x[2]), $tt);
        $out[] = [$data, $ore[0] ?? null, $ore[1] ?? null];
    }
    return $out;
}
// Aggiunge appuntamenti senza toccare quelli esistenti; salta quelli già presenti (stesso titolo, giorno e ora)
function importa_eventi(array $righe, string $titolo, string $tipo, ?int $cliente_id = null, ?string $luogo = null): array {
    $aggiunti = 0; $saltati = 0;
    foreach ($righe as [$data, $ini, $fine]) {
        $c = (int)val('SELECT COUNT(*) FROM eventi WHERE titolo=? AND data=? AND ' . ($ini ? 'ora_inizio=?' : 'ora_inizio IS NULL'), $ini ? [$titolo, $data, $ini] : [$titolo, $data]);
        if ($c) { $saltati++; continue; }
        insert('eventi', ['titolo' => $titolo, 'tipo' => $tipo, 'cliente_id' => $tipo === 'personale' ? null : $cliente_id, 'data' => $data,
            'ora_inizio' => $ini, 'ora_fine' => $fine, 'luogo' => $luogo]);
        $aggiunti++;
    }
    return [$aggiunti, $saltati];
}
// pacchetti di date pronti da importare (cartella importa/)
function pacchetti_da_importare(): array {
    $out = [];
    if (spazio() !== '') return $out; // i pacchetti preparati riguardano solo lo spazio principale
    foreach (glob(APP_ROOT . '/importa/*.json') ?: [] as $f) {
        $k = 'importato_' . basename($f, '.json');
        if (setting($k) !== '') continue;
        $j = json_decode((string)file_get_contents($f), true);
        if (!empty($j['righe'])) $out[basename($f, '.json')] = $j;
    }
    return $out;
}

function app_nome(): string { return setting('app_nome', 'Nana'); }

// ---------- Allegati ----------
function upload_dir(): string {
    $d = APP_ROOT . '/uploads' . (spazio() !== '' ? '/' . rtrim(spazio(), '_') : '');
    if (!is_dir($d)) mkdir($d, 0755, true);
    return $d;
}
function salva_allegato(string $entita, int $id, array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Caricamento non riuscito (file troppo grande?).';
    if ($file['size'] > 20 * 1024 * 1024) return 'Il file supera i 20 MB.';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ok = ['pdf','jpg','jpeg','png','webp','gif','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','odt','ods','p7m','xml','svg','ai','psd','mp4','mov'];
    if (!in_array($ext, $ok, true)) return 'Tipo di file non ammesso.';
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], upload_dir() . '/' . $name)) return 'Impossibile salvare il file.';
    insert('allegati', ['entita' => $entita, 'entita_id' => $id, 'nome' => mb_substr(basename($file['name']), 0, 190), 'file' => $name, 'dimensione' => (int)$file['size']]);
    return null;
}
function allegati_box(string $entita, int $id): string {
    $rows = all('SELECT * FROM allegati WHERE entita=? AND entita_id=? ORDER BY created_at DESC', [$entita, $id]);
    ob_start(); ?>
    <div class="files">
      <?php foreach ($rows as $a): ?>
        <div class="file-row">
          <a href="file.php?id=<?= $a['id'] ?>" target="_blank"><?= icon('file') ?> <?= h($a['nome']) ?></a>
          <span class="muted small"><?= round($a['dimensione'] / 1024) ?> KB · <?= d($a['created_at']) ?></span>
          <form method="post" action="<?= url('azioni') ?>" class="inline" data-conferma="Eliminare il file?">
            <?= csrf() ?><input type="hidden" name="azione" value="elimina_allegato"><input type="hidden" name="id" value="<?= $a['id'] ?>">
            <button class="icon-btn" title="Elimina"><?= icon('trash') ?></button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$rows): ?><p class="muted small">Nessun file.</p><?php endif; ?>
      <form method="post" action="<?= url('azioni') ?>" enctype="multipart/form-data" class="upload-form">
        <?= csrf() ?><input type="hidden" name="azione" value="carica_allegato">
        <input type="hidden" name="entita" value="<?= h($entita) ?>"><input type="hidden" name="entita_id" value="<?= $id ?>">
        <input type="file" name="file" required>
        <button class="btn btn-sm">Carica</button>
      </form>
    </div>
    <?php return ob_get_clean();
}

// ---------- Icone (SVG inline) ----------
function icon(string $n): string {
    static $i = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.3 0 4 1.4 4.5 4"/>',
        'funnel' => '<path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/>',
        'doc' => '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5M9 13h7M9 17h7M9 9h3"/>',
        'euro' => '<path d="M18 6.5A7 7 0 1 0 18 17.5"/><path d="M4 10h10M4 14h10"/>',
        'contract' => '<path d="M5 3h14v18H5z"/><path d="M9 8h6M9 12h6M9 16h3"/><path d="m14 17 1.5 1.5L19 15"/>',
        'folder' => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
        'check' => '<path d="m4 12.5 5 5L20 6.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="1"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'file' => '<path d="M6 2h8l5 5v15H6z"/><path d="M14 2v5h5"/>',
        'print' => '<path d="M6 9V3h12v6M6 18H3v-8h18v8h-3"/><path d="M6 14h12v7H6z"/>',
        'alert' => '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18v.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'logout' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'phone' => '<path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A18 18 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 6 9 7 9-7"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 6 0l3-3a4 4 0 0 0-6-6l-1 1"/><path d="M14 10a4 4 0 0 0-6 0l-3 3a4 4 0 0 0 6 6l1-1"/>',
        'copy' => '<rect x="8" y="8" width="13" height="13" rx="1"/><path d="M16 8V3H3v13h5"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($i[$n] ?? '') . '</svg>';
}

// Data in cui va emessa la prossima fattura di un contratto (null se non serve)
function contr_prossima_fattura(array $k): ?string {
    if ($k['stato'] !== 'attivo') return null;
    $mesi = PERIODO_MESI[$k['periodicita']];
    $last = val('SELECT MAX(data) FROM fatture WHERE contratto_id=? AND annullata=0', [$k['id']]);
    if ($mesi === 0) return $last ? null : $k['data_inizio'];
    if (!$last) return $k['data_inizio'];
    $dt = new DateTime($last);
    $dt->modify('first day of this month')->modify("+$mesi months");
    $giorno = min((int)date('j', strtotime($k['data_inizio'])), (int)$dt->format('t'));
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), $giorno);
    $next = $dt->format('Y-m-d');
    $fine = contr_scadenza($k);
    if ($fine && $next > $fine && !$k['rinnovo_automatico']) return null;
    return $next;
}


// ---------- Aggiornamenti del database (automatici, non toccano i dati) ----------
const DB_VERSIONE = 2;
function migra_db(): void {
    $pdo = db();
    try { $v = (int)$pdo->query("SELECT valore FROM impostazioni WHERE chiave='db_versione'")->fetchColumn(); }
    catch (Throwable $e) { return; }
    if ($v >= DB_VERSIONE) return;
    if ($v < 2) {
        $cols = array_column($pdo->query('SHOW COLUMNS FROM utenti')->fetchAll(), 'Field');
        if (!in_array('spazio', $cols, true)) $pdo->exec("ALTER TABLE utenti ADD spazio VARCHAR(12) NOT NULL DEFAULT '' AFTER email");
        if (!in_array('ruolo', $cols, true)) $pdo->exec("ALTER TABLE utenti ADD ruolo ENUM('admin','utente') NOT NULL DEFAULT 'utente' AFTER spazio");
        if (!in_array('cambia_password', $cols, true)) $pdo->exec("ALTER TABLE utenti ADD cambia_password TINYINT(1) NOT NULL DEFAULT 0 AFTER ruolo");
        if (!in_array('attivo', $cols, true)) $pdo->exec("ALTER TABLE utenti ADD attivo TINYINT(1) NOT NULL DEFAULT 1 AFTER cambia_password");
        $pdo->exec("UPDATE utenti SET ruolo='admin' WHERE spazio=''");
        // la piattaforma ora si chiama Nana
        $pdo->exec("INSERT INTO impostazioni (chiave, valore) VALUES ('app_nome','Nana') ON DUPLICATE KEY UPDATE valore=IF(valore IN ('Pia',''),'Nana',valore)");
    }
    $pdo->prepare("INSERT INTO impostazioni (chiave, valore) VALUES ('db_versione',?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")->execute([(string)DB_VERSIONE]);
}
if (PHP_SAPI !== 'cli' || !empty($GLOBALS['MIGRA_CLI'])) { try { migra_db(); } catch (Throwable $e) { error_log('Nana migrazione: ' . $e->getMessage()); } }

// Crea le tabelle di un nuovo spazio partendo dallo schema (solo le tabelle dello spazio)
function crea_tabelle_spazio(string $prefisso): void {
    $f = APP_ROOT . '/inc/schema.sql';
    if (!is_file($f)) throw new RuntimeException('Manca il file inc/schema.sql: ricarica i file di Nana sul server.');
    $sql = (string)file_get_contents($f);
    $prima = spazio();
    $GLOBALS['SPAZIO'] = $prefisso;
    try {
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
            if (!preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $stmt, $m) || !in_array($m[1], TABELLE_SPAZIO, true)) continue;
            $stmt = preg_replace('/CONSTRAINT \w+ /', '', $stmt); // i nomi dei vincoli devono essere unici nel database
            db()->exec(tsql($stmt));
        }
    } finally { $GLOBALS['SPAZIO'] = $prima; setting_reset(); }
}
// Valori iniziali delle impostazioni di uno spazio nuovo (solo quelli che mancano)
function impostazioni_iniziali(string $prefisso, string $nome = ''): void {
    $prima = spazio();
    set_spazio($prefisso);
    try {
        $def = ['app_nome' => 'Nana', 'azienda_nome' => $nome, 'colore' => COLORE_DEFAULT, 'iva_default' => '22', 'giorni_pagamento' => '30', 'preavviso_default' => '30',
                'condizioni_default' => "Pagamento: 50% all'accettazione, saldo alla consegna.\nI prezzi si intendono IVA esclusa.\nIl preventivo è valido 30 giorni dalla data di emissione."];
        foreach ($def as $k => $v) q('INSERT IGNORE INTO impostazioni (chiave, valore) VALUES (?,?)', [$k, $v]);
    } finally { set_spazio($prima); }
    $dir = APP_ROOT . '/uploads/' . rtrim($prefisso, '_');
    if ($prefisso !== '' && !is_dir($dir)) @mkdir($dir, 0755, true);
    if ($prefisso !== '' && !is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\n");
}
// Controlla che lo spazio abbia tutte le sue tabelle; se ne manca qualcuna la crea (non tocca i dati esistenti)
function ripara_spazio(string $prefisso, string $nome = ''): bool {
    if ($prefisso === '') return true;
    $esistenti = q_raw('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [str_replace('_', '\\_', $prefisso) . '%'])->fetchAll(PDO::FETCH_COLUMN);
    $esistenti = array_map('strtolower', $esistenti);
    $mancano = array_filter(TABELLE_SPAZIO, fn($t) => !in_array(strtolower($prefisso . $t), $esistenti, true));
    if (!$mancano) return true;
    crea_tabelle_spazio($prefisso);
    impostazioni_iniziali($prefisso, $nome);
    return false;
}
function elimina_spazio(string $prefisso): void {
    if (!preg_match('/^u\d+_$/', $prefisso)) return; // lo spazio principale non si elimina mai
    db()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (TABELLE_SPAZIO as $t) db()->exec("DROP TABLE IF EXISTS `$prefisso$t`");
    db()->exec('SET FOREIGN_KEY_CHECKS=1');
    $dir = APP_ROOT . '/uploads/' . rtrim($prefisso, '_');
    if (is_dir($dir)) { foreach (glob("$dir/*") ?: [] as $f) @unlink($f); @unlink("$dir/.htaccess"); @rmdir($dir); }
}

// spazio di chi ha fatto l'accesso
if (!empty($_SESSION['uid'])) {
    $sp = (string)($_SESSION['spazio'] ?? '');
    if (preg_match('/^(u\d+_)?$/', $sp)) $GLOBALS['SPAZIO'] = $sp;
}
