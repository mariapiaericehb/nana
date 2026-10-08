<?php
require __DIR__ . '/inc/bootstrap.php';
if (user()) redirect('index.php');
$err = '';
$fase = !empty($_SESSION['pending_uid']) ? 'codice' : 'password';
if (!empty($_SESSION['pending_at']) && time() - $_SESSION['pending_at'] > 300) { unset($_SESSION['pending_uid'], $_SESSION['pending_at']); $fase = 'password'; }
if (!empty($_SESSION['scaduta'])) { $err = 'Per sicurezza sei stata disconnessa dopo un periodo di inattività.'; unset($_SESSION['scaduta']); }

function entra(array $u): never {
    session_regenerate_id(true);
    unset($_SESSION['pending_uid'], $_SESSION['pending_at']);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['spazio'] = (string)$u['spazio'];
    $_SESSION['since'] = $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('DELETE FROM login_tentativi WHERE ip=?', [$_SERVER['REMOTE_ADDR'] ?? '']);
    registra_accesso((int)$u['id'], 'ok');
    $to = $_SESSION['after_login'] ?? 'index.php';
    unset($_SESSION['after_login']);
    if (!preg_match('#^/[^/\\\\]#', $to) && !str_starts_with($to, 'index.php')) $to = 'index.php';
    redirect($to);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    q('DELETE FROM login_tentativi WHERE created_at < NOW() - INTERVAL 1 DAY');
    $n = (int)val('SELECT COUNT(*) FROM login_tentativi WHERE ip=? AND created_at > NOW() - INTERVAL 15 MINUTE', [$ip]);
    $tot = (int)val('SELECT COUNT(*) FROM login_tentativi WHERE created_at > NOW() - INTERVAL 15 MINUTE');
    if ($n >= 6 || $tot >= 30) {
        $err = 'Troppi tentativi. Riprova tra 15 minuti.';
    } elseif ($fase === 'password') {
        $u = one('SELECT * FROM utenti WHERE email=?', [mb_strtolower(post('email'))]);
        // stessa durata anche se l'email non esiste, per non rivelare quali account ci sono
        $ok = password_verify((string)post('password'), $u['password_hash'] ?? '$2y$10$MqGDoKhn9GLC9xo19ptT4ueAT/f5NHCYXVpT/hn3rsk6qkRUzBCVC');
        if ($u && $ok && empty($u['attivo'])) { $u = null; $ok = false; } // utente sospeso
        if ($u && $ok) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) update('utenti', (int)$u['id'], ['password_hash' => password_hash((string)post('password'), PASSWORD_DEFAULT)]);
            global $CONFIG;
            if (!empty($u['totp_secret']) && empty($CONFIG['disattiva_2fa'])) {
                session_regenerate_id(true);
                $_SESSION['pending_uid'] = (int)$u['id'];
                $_SESSION['pending_at'] = time();
                redirect('login.php');
            }
            entra($u);
        }
        insert('login_tentativi', ['ip' => $ip]);
        if ($u) registra_accesso((int)$u['id'], 'password errata');
        $err = 'Email o password non corretti.';
    } else {
        if (post('annulla')) { unset($_SESSION['pending_uid'], $_SESSION['pending_at']); redirect('login.php'); }
        $u = one('SELECT * FROM utenti WHERE id=?', [$_SESSION['pending_uid']]);
        $step = null;
        if ($u && totp_check((string)$u['totp_secret'], (string)post('codice'), $step) && $step > (int)$u['totp_ultimo']) {
            update('utenti', (int)$u['id'], ['totp_ultimo' => $step]); // lo stesso codice non vale due volte
            entra($u);
        }
        insert('login_tentativi', ['ip' => $ip]);
        if ($u) registra_accesso((int)$u['id'], 'codice errato');
        $err = 'Codice non valido. Usa quello che vedi adesso nell\'app.';
    }
}
$nome = app_nome();
$logo = setting('logo');
?><!doctype html>
<html lang="it"<?= tema_class() ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive"><title>Accedi · <?= h($nome) ?></title>
<link rel="stylesheet" href="<?= asset("assets/style.css") ?>"><style><?= theme_css() ?></style><link rel="icon" href="<?= asset('assets/nana-icona.svg') ?>">
</head>
<body class="login-page">
<form method="post" class="login-card" autocomplete="on">
  <div class="login-brand"><img class="brand-cat" src="<?= asset('assets/nana-gatto.svg') ?>" alt=""><span class="brand-word"><?= h($nome) ?></span></div>
  <?= csrf() ?>
  <?php if ($fase === 'password'): ?>
    <h1>Ciao!</h1>
    <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>
    <label>Email<input type="email" name="email" required autofocus autocomplete="username" value="<?= h(post('email')) ?>"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block">Accedi</button>
  <?php else: ?>
    <h1>Il codice, per favore.</h1>
    <p class="muted small">Apri l'app di autenticazione sul telefono e scrivi il codice di 6 cifre di HypeBang.</p>
    <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>
    <label>Codice<input name="codice" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus autocomplete="one-time-code" class="code-input"></label>
    <button class="btn btn-primary btn-block">Verifica</button>
    <p class="small" style="margin-top:14px;text-align:center"><button class="link-btn" name="annulla" value="1" formnovalidate>Torna indietro</button></p>
  <?php endif; ?>
</form>
</body></html>
