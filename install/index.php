<?php
// Installazione guidata Nana
declare(strict_types=1);
define('INSTALL', 1);
$root = dirname(__DIR__);
$done = false; $err = '';
if (is_file($root . '/config.php')) {
    $msg = 'La piattaforma è già installata. Per sicurezza elimina la cartella <b>install</b> dal server.';
}
if (PHP_SAPI !== 'cli') { header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($msg) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $c = [
        'db_host' => trim($_POST['db_host'] ?? 'localhost'),
        'db_name' => trim($_POST['db_name'] ?? ''),
        'db_user' => trim($_POST['db_user'] ?? ''),
        'db_pass' => (string)($_POST['db_pass'] ?? ''),
    ];
    $nome = trim($_POST['nome'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email non valida.');
        if (strlen($pw) < 10) throw new Exception('La password deve avere almeno 10 caratteri.');
        if (version_compare(PHP_VERSION, '8.1', '<')) throw new Exception('Serve PHP 8.1 o superiore (ora: ' . PHP_VERSION . '). Cambialo da Site Tools → Devs → PHP Manager.');
        $pdo = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(dirname(__DIR__) . '/inc/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) $pdo->exec($stmt);
        if ((int)$pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn() === 0) {
            $s = $pdo->prepare("INSERT INTO utenti (nome, email, password_hash, ruolo, spazio) VALUES (?,?,?,'admin','')");
            $s->execute([$nome ?: 'Admin', $email, password_hash($pw, PASSWORD_DEFAULT)]);
        }
        $ins = $pdo->prepare('INSERT IGNORE INTO impostazioni (chiave, valore) VALUES (?,?)');
        foreach ([
            'app_nome' => 'Nana', 'db_versione' => '2', 'azienda_nome' => 'HypeBang', 'colore' => '#dc564a', 'logo' => is_file($root . '/assets/logo.svg') ? 'assets/logo.svg' : '', 'iva_default' => '22', 'giorni_pagamento' => '30',
            'preavviso_default' => '30',
            'condizioni_default' => "Pagamento: 50% all'accettazione, saldo alla consegna.\nI prezzi si intendono IVA esclusa.\nIl preventivo è valido 30 giorni dalla data di emissione.",
        ] as $k => $v) $ins->execute([$k, $v]);
        if (!empty($_POST['demo'])) {
            require __DIR__ . '/demo.php';
            demo_data($pdo);
        }
        $config = "<?php\nreturn " . var_export($c + [
            'timezone' => 'Europe/Rome', 'debug' => false,
            'force_https' => true, 'disattiva_2fa' => false, 'mail_enabled' => false,
        ], true) . ";\n";
        if (@file_put_contents($root . '/config.php', $config) === false) throw new Exception('Non riesco a scrivere config.php: controlla i permessi della cartella.');
        @chmod($root . '/config.php', 0640);
        @mkdir($root . '/uploads', 0755);
        $done = true;
    } catch (PDOException $ex) {
        $err = 'Connessione al database non riuscita: ' . $ex->getMessage();
    } catch (Exception $ex) {
        $err = $ex->getMessage();
    }
}
?><!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installazione · Nana</title>
<meta name="robots" content="noindex, nofollow, noarchive">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">
<div class="login-card install">
  <div class="login-brand"><span class="brand-mark">H</span><span>Nana</span></div>
  <?php if (isset($msg)): ?>
    <h1>Già installata</h1><p><?= $msg ?></p><a class="btn btn-primary" href="../">Vai alla piattaforma</a>
  <?php elseif ($done): ?>
    <h1>Fatto!</h1>
    <p>La piattaforma è pronta. <b>Ora elimina la cartella <code>install</code></b> dal server (File Manager di SiteGround), poi accedi.</p>
    <a class="btn btn-primary" href="../login.php">Accedi</a>
  <?php else: ?>
    <h1>Installazione</h1>
    <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <h3>Database</h3>
      <p class="muted small">Su SiteGround: Site Tools → Site → MySQL. Crea un database e un utente, e collegali.</p>
      <label>Host<input name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>" required></label>
      <label>Nome database<input name="db_name" value="<?= e($_POST['db_name'] ?? '') ?>" required></label>
      <label>Utente database<input name="db_user" value="<?= e($_POST['db_user'] ?? '') ?>" required></label>
      <label>Password database<input name="db_pass" type="password"></label>
      <h3>Il tuo accesso</h3>
      <label>Nome<input name="nome" value="<?= e($_POST['nome'] ?? '') ?>"></label>
      <label>Email<input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required></label>
      <label>Password (almeno 10 caratteri)<input name="password" type="password" minlength="10" required></label>
      <label class="check"><input type="checkbox" name="demo" value="1"> Carica dati di esempio (si possono cancellare dopo)</label>
      <button class="btn btn-primary btn-block">Installa</button>
    </form>
  <?php endif; ?>
</div>
</body></html>
