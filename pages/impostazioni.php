<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$campi = [
    'azienda_nome' => 'Nome dell\'attività (sui preventivi)', 'azienda_ragione_sociale' => 'Ragione sociale / nome e cognome',
    'azienda_piva' => 'Partita IVA', 'azienda_email' => 'Email', 'azienda_telefono' => 'Telefono', 'azienda_sito' => 'Sito',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'azienda') {
        foreach (array_keys($campi) as $k) set_setting($k, post($k));
        set_setting('app_nome', post('app_nome') ?: 'Nana');
        foreach (['azienda_indirizzo', 'azienda_iban', 'nota_fiscale', 'condizioni_default', 'listino'] as $k) set_setting($k, post($k));
        set_setting('tema', post('tema') === 'verde' ? 'verde' : '');
        set_setting('colore', preg_match('/^#[0-9a-fA-F]{6}$/', post('colore')) ? post('colore') : COLORE_DEFAULT);
        foreach (['iva_default', 'ritenuta_default'] as $k) set_setting($k, (string)dec(post($k)));
        foreach (['giorni_pagamento', 'preavviso_default'] as $k) set_setting($k, (string)max(0, (int)post($k)));
        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $svg_ok = $ext !== 'svg' || !preg_match('/<script|\bon[a-z]+\s*=|javascript:|<foreignObject|<iframe|<embed|<object/i', (string)file_get_contents($_FILES['logo']['tmp_name']));
            if (!$svg_ok) flash('Questo SVG contiene codice attivo e non è sicuro: esportalo di nuovo come SVG semplice o PNG.', 'err');
            elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true) && $_FILES['logo']['size'] < 2 * 1024 * 1024) {
                $base = 'assets/logo' . (spazio() !== '' ? '-' . rtrim(spazio(), '_') : '');
                foreach (glob(APP_ROOT . "/$base.*") as $old) @unlink($old);
                move_uploaded_file($_FILES['logo']['tmp_name'], APP_ROOT . "/$base.$ext");
                set_setting('logo', "$base.$ext?v=" . time());
            } else flash('Il logo deve essere PNG, JPG, SVG o WEBP sotto i 2 MB.', 'err');
        }
        if (post('togli_logo')) { foreach (glob(APP_ROOT . '/assets/logo' . (spazio() !== '' ? '-' . rtrim(spazio(), '_') : '') . '.*') as $old) @unlink($old); set_setting('logo', ''); }
        flash('Impostazioni salvate.');
        redirect(url('impostazioni'));
    }
    if ($az === 'account') {
        $u = one('SELECT * FROM utenti WHERE id=?', [user()['id']]);
        if (!password_verify((string)post('attuale'), $u['password_hash'])) { flash('La password attuale non è corretta.', 'err'); redirect(url('impostazioni') . '#account'); }
        $data = ['nome' => post('nome') ?: $u['nome']];
        if (filter_var(post('email'), FILTER_VALIDATE_EMAIL)) $data['email'] = mb_strtolower(post('email'));
        if (post('nuova') !== '') {
            if (strlen(post('nuova')) < 10) { flash('La nuova password deve avere almeno 10 caratteri.', 'err'); redirect(url('impostazioni') . '#account'); }
            $data['password_hash'] = password_hash(post('nuova'), PASSWORD_DEFAULT);
        }
        update('utenti', (int)$u['id'], $data);
        flash('Account aggiornato.');
        redirect(url('impostazioni'));
    }
    if (in_array($az, ['2fa_attiva', '2fa_disattiva', 'ical', 'ical_nuovo', 'backup'], true)) {
        $u = one('SELECT * FROM utenti WHERE id=?', [user()['id']]);
        if (!password_verify((string)post('password'), $u['password_hash'])) { flash('Password non corretta.', 'err'); redirect(url('impostazioni') . '#sicurezza'); }
        if ($az === '2fa_attiva') {
            $sec = (string)($_SESSION['totp_nuovo'] ?? '');
            $step = null;
            if ($sec && totp_check($sec, (string)post('codice'), $step)) {
                update('utenti', (int)$u['id'], ['totp_secret' => $sec, 'totp_ultimo' => $step]);
                unset($_SESSION['totp_nuovo']);
                registra_accesso((int)$u['id'], 'verifica in 2 passaggi attivata');
                flash('Verifica in due passaggi attiva. Da ora, oltre alla password, ti chiederemo il codice dell\'app.');
            } else flash('Il codice non corrisponde. Controlla che l\'ora del telefono sia giusta e riprova.', 'err');
        }
        if ($az === '2fa_disattiva') {
            update('utenti', (int)$u['id'], ['totp_secret' => null, 'totp_ultimo' => null]);
            registra_accesso((int)$u['id'], 'verifica in 2 passaggi disattivata');
            flash('Verifica in due passaggi disattivata.');
        }
        if ($az === 'ical') {
            $on = post('attivo') === '1';
            set_setting('ical_attivo', $on ? '1' : '0');
            if ($on && strlen(setting('ical_token')) < 32) set_setting('ical_token', bin2hex(random_bytes(24)));
            flash($on ? 'Calendario per il telefono attivato. Trovi l\'indirizzo in fondo alla pagina Calendario.' : 'Calendario per il telefono disattivato: l\'indirizzo non funziona più.');
        }
        if ($az === 'ical_nuovo') {
            set_setting('ical_token', bin2hex(random_bytes(24)));
            flash('Nuovo indirizzo creato: quello vecchio non funziona più. Ricollega il calendario sul telefono.');
        }
        if ($az === 'backup') {
            registra_accesso((int)$u['id'], 'backup scaricato');
            $_SESSION['backup_ok'] = time();
            redirect(url('esporta', ['cosa' => 'backup']));
        }
        redirect(url('impostazioni') . '#sicurezza');
    }
    // ---------- Utenti (solo per l'amministratrice) ----------
    if (is_admin() && in_array($az, ['utente_nuovo', 'utente_password', 'utente_stato', 'utente_elimina'], true)) {
        $uid = (int)post('uid');
        $altro = $uid ? q_raw("SELECT * FROM utenti WHERE id=? AND spazio<>''", [$uid])->fetch() : null; // mai lo spazio principale
        if ($az === 'utente_nuovo') {
            $email = mb_strtolower(post('email'));
            $pw = (string)post('password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Email non valida.', 'err');
            elseif (q_raw('SELECT 1 FROM utenti WHERE email=?', [$email])->fetchColumn()) flash('Esiste già un utente con questa email.', 'err');
            elseif (strlen($pw) < 4) flash('La password temporanea deve avere almeno 4 caratteri.', 'err');
            else {
                try {
                    q_raw("INSERT INTO utenti (nome, email, password_hash, ruolo, spazio, cambia_password) VALUES (?,?,?,'utente','',1)", [post('nome') ?: $email, $email, password_hash($pw, PASSWORD_DEFAULT)]);
                    $nid = (int)db()->lastInsertId();
                    $pref = "u{$nid}_";
                    q_raw('UPDATE utenti SET spazio=? WHERE id=?', [$pref, $nid]);
                    crea_tabelle_spazio($pref);
                    impostazioni_iniziali($pref, (string)post('nome'));
                } catch (Throwable $e) {
                    // se qualcosa va storto non resta un utente a metà
                    if (!empty($nid)) { elimina_spazio("u{$nid}_"); q_raw('DELETE FROM utenti WHERE id=?', [$nid]); }
                    flash('Non sono riuscita a creare l\'utente: ' . $e->getMessage(), 'err');
                    redirect(url('impostazioni') . '#utenti');
                }
                flash('Utente creato. Al primo accesso dovrà scegliere una password sua.');
            }
        }
        if ($altro && $az === 'utente_password') {
            $pw = (string)post('password');
            if (strlen($pw) < 4) flash('La password temporanea deve avere almeno 4 caratteri.', 'err');
            else { q_raw('UPDATE utenti SET password_hash=?, cambia_password=1, totp_secret=NULL, totp_ultimo=NULL WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $uid]); flash('Password temporanea impostata per ' . $altro['email'] . '. Verifica in due passaggi azzerata.'); }
        }
        if ($altro && $az === 'utente_stato') {
            q_raw('UPDATE utenti SET attivo=1-attivo WHERE id=?', [$uid]);
            flash($altro['attivo'] ? $altro['email'] . ' sospeso: non può più entrare, i suoi dati restano.' : $altro['email'] . ' riattivato.');
        }
        if ($altro && $az === 'utente_elimina') {
            if (post('conferma') !== 'ELIMINA') flash('Per eliminare scrivi ELIMINA.', 'err');
            else {
                elimina_spazio((string)$altro['spazio']);
                foreach (glob(APP_ROOT . '/assets/logo-' . rtrim($altro['spazio'], '_') . '.*') ?: [] as $f) @unlink($f);
                q_raw('DELETE FROM accessi WHERE utente_id=?', [$uid]);
                q_raw('DELETE FROM utenti WHERE id=?', [$uid]);
                flash($altro['email'] . ' eliminato con tutti i suoi dati.');
            }
        }
        redirect(url('impostazioni') . '#utenti');
    }
    if ($az === 'elimina_demo') {
        $n = (int)val("SELECT COUNT(*) FROM clienti WHERE fonte='Dati di esempio'");
        q("DELETE FROM clienti WHERE fonte='Dati di esempio'");
        q("DELETE FROM eventi WHERE cliente_id IS NULL AND note='[esempio]'");
        q("DELETE FROM task WHERE cliente_id IS NULL AND descrizione='[esempio]'");
        flash("Eliminati $n clienti di esempio con tutti i loro dati.");
        redirect(url('impostazioni'));
    }
}
$u = user();
$demo = (int)val("SELECT COUNT(*) FROM clienti WHERE fonte='Dati di esempio'");
$s = fn($k, $d = '') => h(setting($k, $d));

layout_start('Impostazioni', 'impostazioni');
page_head('Impostazioni');
?>
<form method="post" class="card form" enctype="multipart/form-data">
  <?= csrf() ?><input type="hidden" name="azione" value="azienda">
  <h2>La tua piattaforma</h2>
  <div class="grid g3"><label>Nome nel menu<input name="app_nome" value="<?= h(app_nome()) ?>" maxlength="24"></label></div>
  <h2>La tua attività</h2>
  <p class="muted small">Questi dati compaiono in testa ai preventivi.</p>
  <div class="grid g3">
    <?php foreach ($campi as $k => $l): ?><label><?= $l ?><input name="<?= $k ?>" value="<?= $s($k) ?>"></label><?php endforeach; ?>
    <label class="span2">Indirizzo<textarea name="azienda_indirizzo" rows="2"><?= $s('azienda_indirizzo') ?></textarea></label>
    <label>IBAN e banca <span class="hint">sul preventivo</span><textarea name="azienda_iban" rows="2"><?= $s('azienda_iban') ?></textarea></label>
    <label>Aspetto<select name="tema"><option value="">Classico (prugna e rosso)</option><option value="verde"<?= tema() === 'verde' ? ' selected' : '' ?>>Verde e rosa</option></select></label>
    <label>Colore principale <span class="hint">solo tema classico</span><input type="color" name="colore" value="<?= h(colore()) ?>"></label>
    <label>Logo <span class="hint">PNG, SVG o JPG</span><input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp"></label>
    <?php if (setting('logo')): ?><div class="logo-prev"><img src="<?= $s('logo') ?>" alt=""><label class="check"><input type="checkbox" name="togli_logo" value="1"> Togli logo</label></div><?php endif; ?>
  </div>

  <h2>Valori predefiniti</h2>
  <div class="grid g4">
    <label>IVA % <span class="hint">0 se forfettario</span><input name="iva_default" inputmode="decimal" value="<?= $s('iva_default', '22') ?>"></label>
    <label>Ritenuta d'acconto %<input name="ritenuta_default" inputmode="decimal" value="<?= $s('ritenuta_default', '0') ?>"></label>
    <label>Giorni per pagare<input type="number" name="giorni_pagamento" value="<?= $s('giorni_pagamento', '30') ?>"></label>
    <label>Preavviso gestioni (giorni)<input type="number" name="preavviso_default" value="<?= $s('preavviso_default', '30') ?>"></label>
    <label class="span-all">Condizioni standard dei preventivi<textarea name="condizioni_default" rows="4"><?= $s('condizioni_default') ?></textarea></label>
    <label class="span-all">Dicitura fiscale a piè di pagina <span class="hint">es. per il regime forfettario</span><input name="nota_fiscale" value="<?= $s('nota_fiscale') ?>" placeholder="Operazione in franchigia da IVA ai sensi dell'art. 1, commi 54-89, L. 190/2014"></label>
  </div>

  <h2 id="listino">Listino servizi</h2>
  <p class="muted small">Un servizio per riga, nella forma <code>Descrizione | prezzo</code>. Li ritrovi nel menu "Dal listino" quando fai un preventivo.</p>
  <textarea name="listino" rows="7" class="mono" placeholder="Gestione social (2 canali, 12 post al mese) | 800&#10;Shooting fotografico mezza giornata | 450&#10;Strategia e piano editoriale | 1200"><?= $s('listino') ?></textarea>
  <div class="form-actions"><button class="btn btn-primary">Salva</button></div>
</form>

<form method="post" class="card form" id="account">
  <?= csrf() ?><input type="hidden" name="azione" value="account">
  <h2>Il tuo accesso</h2>
  <div class="grid g4">
    <label>Nome<input name="nome" value="<?= h($u['nome']) ?>"></label>
    <label>Email<input type="email" name="email" value="<?= h($u['email']) ?>"></label>
    <label>Nuova password <span class="hint">vuoto = non cambia</span><input type="password" name="nuova" minlength="10" autocomplete="new-password"></label>
    <label>Password attuale *<input type="password" name="attuale" required autocomplete="current-password"></label>
  </div>
  <div class="form-actions"><button class="btn btn-primary">Aggiorna</button></div>
</form>

<?php
$u2 = one('SELECT totp_secret FROM utenti WHERE id=?', [$u['id']]);
$ha2fa = !empty($u2['totp_secret']);
if (!$ha2fa && empty($_SESSION['totp_nuovo'])) $_SESSION['totp_nuovo'] = b32_random();
$sec = $_SESSION['totp_nuovo'] ?? '';
$uri = 'otpauth://totp/' . rawurlencode(app_nome() . ':' . $u['email']) . '?secret=' . $sec . '&issuer=' . rawurlencode(app_nome());
$accessi = all('SELECT * FROM accessi WHERE utente_id=? ORDER BY created_at DESC LIMIT 12', [$u['id']]);
$pw = '<label class="pw-confirm">Conferma con la tua password<input type="password" name="password" required autocomplete="current-password"></label>';
?>
<section class="card" id="sicurezza">
  <h2>Sicurezza e privacy</h2>
  <div class="sec-grid">
    <div class="sec-box">
      <h3>Verifica in due passaggi <?= $ha2fa ? badge('attivo', 'Attiva') : badge('scaduta', 'Non attiva') ?></h3>
      <?php if ($ha2fa): ?>
        <p class="small">Per entrare servono la password e il codice dell'app sul telefono. Anche se qualcuno scoprisse la password, non potrebbe entrare.</p>
        <form method="post" data-conferma="Disattivare la verifica in due passaggi?"><?= csrf() ?><input type="hidden" name="azione" value="2fa_disattiva"><?= $pw ?><button class="btn btn-sm">Disattiva</button></form>
      <?php else: ?>
        <p class="small"><b>Consigliata.</b> Oltre alla password ti chiederà un codice dall'app del telefono (Google Authenticator, Authy, 1Password…).</p>
        <ol class="small steps">
          <li>Nell'app tocca "+" e inquadra il codice QR, oppure inserisci a mano la chiave.</li>
          <li>Scrivi qui sotto il codice di 6 cifre che compare.</li>
        </ol>
        <div class="qr-row"><div id="qr" data-uri="<?= h($uri) ?>"></div><code class="mono key"><?= h(trim(chunk_split($sec, 4, ' '))) ?></code></div>
        <form method="post"><?= csrf() ?><input type="hidden" name="azione" value="2fa_attiva">
          <label>Codice dell'app<input name="codice" inputmode="numeric" maxlength="7" required autocomplete="one-time-code" class="code-input"></label><?= $pw ?>
          <button class="btn btn-primary btn-sm">Attiva</button></form>
      <?php endif; ?>
    </div>

    <div class="sec-box">
      <h3>Calendario sul telefono <?= setting('ical_attivo') === '1' ? badge('attivo', 'Attivo') : badge('concluso', 'Spento') ?></h3>
      <p class="small">Se lo attivi, scadenze e appuntamenti (con i nomi dei clienti) compaiono nel calendario del telefono. Quei dati passano anche da Google o Apple, per questo è spento finché non decidi tu.</p>
      <form method="post" class="row wrap"><?= csrf() ?><input type="hidden" name="azione" value="ical"><input type="hidden" name="attivo" value="<?= setting('ical_attivo') === '1' ? '0' : '1' ?>"><?= $pw ?>
        <button class="btn btn-sm"><?= setting('ical_attivo') === '1' ? 'Disattiva' : 'Attiva' ?></button>
        <?php if (setting('ical_attivo') === '1'): ?><button class="link-btn" name="azione" value="ical_nuovo">Crea un nuovo indirizzo (quello vecchio smette di funzionare)</button><?php endif; ?>
      </form>
    </div>

    <div class="sec-box">
      <h3>Backup completo</h3>
      <p class="small">Contiene tutti i dati dei clienti: conservalo in un posto sicuro e non mandarlo per email. SiteGround fa già un backup automatico ogni giorno.</p>
      <form method="post" class="row wrap"><?= csrf() ?><input type="hidden" name="azione" value="backup"><?= $pw ?><button class="btn btn-sm"><?= icon('download') ?> Scarica backup (.sql)</button></form>
      <p class="small" style="margin-top:10px"><a href="<?= url('esporta', ['cosa' => 'clienti']) ?>">Clienti (CSV)</a> · <a href="<?= url('esporta', ['cosa' => 'fatture', 'anno' => date('Y')]) ?>">Fatture <?= date('Y') ?> (CSV)</a></p>
    </div>

    <div class="sec-box">
      <h3>Ultimi accessi</h3>
      <p class="small muted">Se vedi un accesso che non riconosci, cambia subito la password.</p>
      <div class="access-list">
      <?php foreach ($accessi as $a): ?>
        <div class="small"><span class="<?= in_array($a['esito'], ['password errata', 'codice errato'], true) ? 'is-soon' : '' ?>"><?= $a['esito'] === 'ok' ? 'Accesso' : h(ucfirst($a['esito'])) ?></span> · <?= d($a['created_at']) ?> <?= substr($a['created_at'], 11, 5) ?> · <span class="muted"><?= h($a['ip']) ?> · <?= h(browser_breve($a['browser'])) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$accessi): ?><p class="muted small">Nessuno registrato.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <p class="small muted" style="margin:14px 0 0">La piattaforma non usa cookie di tracciamento, non carica nulla da servizi esterni (nemmeno i font) e non è indicizzata dai motori di ricerca. Esci da sola dopo 4 ore senza attività. Gli indirizzi IP dei tentativi di accesso si cancellano dopo un giorno, il registro degli accessi dopo 90.</p>
</section>

<?php if (is_admin()):
  $utenti = q_raw("SELECT u.*, (SELECT MAX(created_at) FROM accessi a WHERE a.utente_id=u.id AND a.esito='ok') ultimo FROM utenti u WHERE u.spazio<>'' ORDER BY u.nome")->fetchAll();
  foreach ($utenti as $x) { try { ripara_spazio((string)$x['spazio'], (string)$x['nome']); } catch (Throwable $e) { flash('Spazio di ' . $x['email'] . ' incompleto: ' . $e->getMessage(), 'err'); } } ?>
<section class="card" id="utenti">
  <h2>Utenti</h2>
  <p class="small muted">Ogni utente ha uno spazio tutto suo: i suoi clienti, preventivi, fatture, calendario e impostazioni. Non vede i tuoi dati e tu non vedi i suoi.</p>
  <?php foreach ($utenti as $x): ?>
    <div class="user-row">
      <div><b><?= h($x['nome']) ?></b> <span class="muted small"><?= h($x['email']) ?></span>
        <?= $x['attivo'] ? '' : badge('disdetto', 'Sospeso') ?><?= $x['cambia_password'] ? ' ' . badge('in_attesa', 'Password temporanea') : '' ?>
        <div class="muted small"><?= $x['ultimo'] ? 'Ultimo accesso ' . d($x['ultimo']) . ' ' . substr($x['ultimo'], 11, 5) : 'Non è ancora entrato' ?></div></div>
      <details class="menu"><summary class="btn btn-sm">Gestisci</summary><div class="menu-list user-menu">
        <form method="post"><?= csrf() ?><input type="hidden" name="azione" value="utente_password"><input type="hidden" name="uid" value="<?= $x['id'] ?>">
          <label class="small">Nuova password temporanea<input name="password" required minlength="4" autocomplete="off"></label><button class="btn btn-sm btn-block">Imposta</button></form>
        <form method="post"><?= csrf() ?><input type="hidden" name="azione" value="utente_stato"><input type="hidden" name="uid" value="<?= $x['id'] ?>">
          <button class="btn btn-sm btn-block"><?= $x['attivo'] ? 'Sospendi accesso' : 'Riattiva accesso' ?></button></form>
        <form method="post"><?= csrf() ?><input type="hidden" name="azione" value="utente_elimina"><input type="hidden" name="uid" value="<?= $x['id'] ?>">
          <label class="small">Per eliminarlo con tutti i suoi dati scrivi ELIMINA<input name="conferma" autocomplete="off" required></label>
          <button class="btn btn-sm btn-danger btn-block" data-conferma="Eliminare <?= h($x['email']) ?> e TUTTI i suoi dati? Non si può annullare.">Elimina utente</button></form>
      </div></details>
    </div>
  <?php endforeach; ?>
  <?php if (!$utenti): ?><p class="small muted">Nessun altro utente.</p><?php endif; ?>
  <details class="add" <?= $utenti ? '' : 'open' ?>><summary class="link-btn"><?= icon('plus') ?> Aggiungi utente</summary>
    <form method="post" class="form" style="margin-top:12px"><?= csrf() ?><input type="hidden" name="azione" value="utente_nuovo">
      <div class="grid g3">
        <label>Nome<input name="nome" placeholder="Nome e cognome"></label>
        <label>Email *<input type="email" name="email" required autocomplete="off"></label>
        <label>Password temporanea * <span class="hint">la cambierà al primo accesso</span><input name="password" required minlength="4" autocomplete="off"></label>
      </div>
      <button class="btn btn-primary btn-sm">Crea utente</button>
    </form>
  </details>
</section>
<?php endif; ?>

<?php if ($demo): ?>
<form method="post" class="card danger-zone" data-conferma="Eliminare tutti i clienti di esempio e i loro preventivi, fatture, progetti?">
  <?= csrf() ?><input type="hidden" name="azione" value="elimina_demo">
  <h2>Dati di esempio</h2>
  <p class="small">Ci sono ancora <?= $demo ?> clienti di esempio. Quando hai finito di provare, eliminali con un clic: i tuoi dati veri restano.</p>
  <button class="btn btn-danger">Elimina i dati di esempio</button>
</form>
<?php endif; ?>
<script src="assets/vendor/qrcode.js"></script>
<?php layout_end();
