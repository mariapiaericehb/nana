<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// Cambio della password temporanea (obbligatorio al primo accesso)
$u = user();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuova = (string)post('nuova');
    if (strlen($nuova) < 10) $err = 'La password deve avere almeno 10 caratteri.';
    elseif ($nuova !== (string)post('conferma')) $err = 'Le due password non coincidono.';
    elseif (in_array(strtolower($nuova), ['1234567890', 'password123', 'qwertyuiop'], true)) $err = 'Questa password è troppo facile da indovinare.';
    else {
        q_raw('UPDATE utenti SET password_hash=?, cambia_password=0 WHERE id=?', [password_hash($nuova, PASSWORD_DEFAULT), $u['id']]);
        registra_accesso((int)$u['id'], 'password cambiata');
        flash('Password salvata. Benvenuto in ' . app_nome() . '!');
        redirect('index.php');
    }
}
layout_start('Scegli la tua password', '');
page_head('Scegli la tua password', '', 'Per sicurezza, al primo accesso sostituisci la password temporanea con una tua.');
?>
<form method="post" class="card form narrow">
  <?= csrf() ?>
  <?php if ($err): ?><div class="flash flash-err"><?= h($err) ?></div><?php endif; ?>
  <div class="grid g2">
    <label>Nuova password <span class="hint">almeno 10 caratteri</span><input type="password" name="nuova" minlength="10" required autocomplete="new-password" autofocus></label>
    <label>Ripetila<input type="password" name="conferma" minlength="10" required autocomplete="new-password"></label>
  </div>
  <p class="small muted">Consiglio: una frase di tre o quattro parole che ricordi facilmente, per esempio "gatto-rosso-sul-divano".</p>
  <div class="form-actions"><button class="btn btn-primary">Salva e continua</button></div>
</form>
<?php layout_end();
