<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$c = $id ? one('SELECT * FROM clienti WHERE id=?', [$id]) : null;
if ($id && !$c) { flash('Cliente non trovato.', 'err'); redirect(url('clienti')); }

// ---------- Salvataggi ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = post('azione');
    if ($az === 'salva') {
        $data = [];
        foreach (['ragione_sociale','nome_breve','settore','fonte','piva','codice_fiscale','codice_sdi','pec','indirizzo','cap','citta','provincia','email','telefono','sito','instagram','note'] as $f)
            $data[$f] = nul(post($f));
        $data['ragione_sociale'] = $data['ragione_sociale'] ?? 'Senza nome';
        $data['stato'] = pick(post('stato'), STATI_CLIENTE, 'cliente');
        $data['colore'] = preg_match('/^#[0-9a-fA-F]{6}$/', post('colore')) ? post('colore') : null;
        if ($data['sito'] && !preg_match('#^https?://#', $data['sito'])) $data['sito'] = 'https://' . $data['sito'];
        if ($id) {
            if ($c['stato'] !== $data['stato']) log_act($id, 'Stato cambiato da ' . STATI_CLIENTE[$c['stato']] . ' a ' . STATI_CLIENTE[$data['stato']]);
            update('clienti', $id, $data);
        } else {
            $id = insert('clienti', $data);
            log_act($id, 'Scheda creata');
            // referente rapido
            if (post('ref_nome') !== '') insert('contatti', ['cliente_id' => $id, 'nome' => post('ref_nome'), 'ruolo' => nul(post('ref_ruolo')), 'email' => nul(post('ref_email')), 'telefono' => nul(post('ref_telefono')), 'principale' => 1]);
        }
        flash('Cliente salvato.');
        redirect(url('cliente', ['id' => $id]));
    }
    if ($az === 'elimina' && $id) {
        // cancella davvero anche i file del cliente e dei suoi contratti, progetti, preventivi e fatture
        $files = all("SELECT a.* FROM allegati a WHERE (a.entita='cliente' AND a.entita_id=?)
            OR (a.entita='contratto' AND a.entita_id IN (SELECT id FROM contratti WHERE cliente_id=?))
            OR (a.entita='progetto' AND a.entita_id IN (SELECT id FROM progetti WHERE cliente_id=?))
            OR (a.entita='preventivo' AND a.entita_id IN (SELECT id FROM preventivi WHERE cliente_id=?))
            OR (a.entita='fattura' AND a.entita_id IN (SELECT id FROM fatture WHERE cliente_id=?))", [$id, $id, $id, $id, $id]);
        foreach ($files as $a) { @unlink(upload_dir() . '/' . basename($a['file'])); delete_row('allegati', (int)$a['id']); }
        delete_row('clienti', $id);
        flash('Cliente eliminato, con tutti i suoi dati.');
        redirect(url('clienti'));
    }
    if ($az === 'contatto' && $id) {
        $cid = (int)post('contatto_id');
        $data = ['cliente_id' => $id, 'nome' => post('nome') ?: 'Senza nome', 'ruolo' => nul(post('ruolo')), 'email' => nul(post('email')), 'telefono' => nul(post('telefono')), 'note' => nul(post('note')), 'principale' => post('principale') ? 1 : 0];
        if ($data['principale']) q('UPDATE contatti SET principale=0 WHERE cliente_id=?', [$id]);
        if ($cid) update('contatti', $cid, $data); else insert('contatti', $data);
        flash('Referente salvato.');
        redirect(url('cliente', ['id' => $id]));
    }
    if ($az === 'elimina_contatto') {
        q('DELETE FROM contatti WHERE id=? AND cliente_id=?', [(int)post('contatto_id'), $id]);
        redirect(url('cliente', ['id' => $id]));
    }
}

// ---------- Modulo (nuovo / modifica) ----------
if (!$c || get('modifica')) {
    $c = $c ?? ['stato' => get('stato') === 'potenziale' ? 'potenziale' : 'cliente'];
    $v = fn($k) => h($c[$k] ?? '');
    layout_start($id ? 'Modifica cliente' : 'Nuovo cliente', 'clienti');
    page_head($id ? 'Modifica ' . ($c['nome_breve'] ?: $c['ragione_sociale']) : 'Nuovo cliente');
    ?>
    <form method="post" class="card form">
      <?= csrf() ?><input type="hidden" name="azione" value="salva">
      <div class="grid g3">
        <label class="span2">Ragione sociale / Nome *<input name="ragione_sociale" required value="<?= $v('ragione_sociale') ?>"></label>
        <label>Nome breve <span class="hint">come lo chiami tu</span><input name="nome_breve" value="<?= $v('nome_breve') ?>"></label>
        <label>Stato<select name="stato"><?= options(STATI_CLIENTE, $c['stato'] ?? 'cliente') ?></select></label>
        <label>Settore<input name="settore" value="<?= $v('settore') ?>" placeholder="Ristorazione, moda…"></label>
        <label>Come ci ha trovato<input name="fonte" value="<?= $v('fonte') ?>" placeholder="Passaparola, Instagram…"></label>
        <label>Email<input type="email" name="email" value="<?= $v('email') ?>"></label>
        <label>Telefono<input name="telefono" value="<?= $v('telefono') ?>"></label>
        <label>Colore <span class="hint">per riconoscerlo al volo</span><input type="color" name="colore" value="<?= $v('colore') ?: '#888888' ?>"></label>
        <label>Sito<input name="sito" value="<?= $v('sito') ?>"></label>
        <label>Instagram<input name="instagram" value="<?= $v('instagram') ?>" placeholder="@account"></label>
      </div>
      <?php if (!$id): ?>
      <h3>Referente</h3>
      <div class="grid g4">
        <label>Nome<input name="ref_nome"></label><label>Ruolo<input name="ref_ruolo"></label>
        <label>Email<input type="email" name="ref_email"></label><label>Telefono<input name="ref_telefono"></label>
      </div>
      <?php endif; ?>
      <details <?= ($c['piva'] ?? '') ? 'open' : '' ?>>
        <summary>Dati di fatturazione</summary>
        <div class="grid g3">
          <label>Partita IVA<input name="piva" value="<?= $v('piva') ?>"></label>
          <label>Codice fiscale<input name="codice_fiscale" value="<?= $v('codice_fiscale') ?>"></label>
          <label>Codice SDI<input name="codice_sdi" value="<?= $v('codice_sdi') ?>" maxlength="7"></label>
          <label>PEC<input name="pec" value="<?= $v('pec') ?>"></label>
          <label class="span2">Indirizzo<input name="indirizzo" value="<?= $v('indirizzo') ?>"></label>
          <label>CAP<input name="cap" value="<?= $v('cap') ?>"></label>
          <label>Città<input name="citta" value="<?= $v('citta') ?>"></label>
          <label>Provincia<input name="provincia" value="<?= $v('provincia') ?>" maxlength="4"></label>
        </div>
      </details>
      <label>Note<textarea name="note" rows="4"><?= $v('note') ?></textarea></label>
      <div class="form-actions">
        <button class="btn btn-primary">Salva</button>
        <a class="btn btn-ghost" href="<?= $id ? url('cliente', ['id' => $id]) : url('clienti') ?>">Annulla</a>
      </div>
    </form>
    <?php if ($id): ?>
    <form method="post" class="danger-zone" data-conferma="Eliminare il cliente e TUTTO quello che lo riguarda (preventivi, fatture, progetti, task)? Non si può annullare. Se hai smesso di lavorarci, conviene impostare lo stato su Ex cliente.">
      <?= csrf() ?><input type="hidden" name="azione" value="elimina">
      <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Elimina cliente</button>
      <span class="muted small">Se avete solo smesso di lavorare insieme, meglio impostare lo stato su "Ex cliente": lo storico resta.</span>
    </form>
    <?php endif;
    layout_end();
    return;
}

// ---------- Scheda ----------
$tab = get('tab', 'panoramica');
$nome = $c['nome_breve'] ?: $c['ragione_sociale'];
$contatti = all('SELECT * FROM contatti WHERE cliente_id=? ORDER BY principale DESC, nome', [$id]);
$fatt = all('SELECT ' . FATT_COLS . ' FROM fatture f WHERE f.cliente_id=? ORDER BY f.data DESC, f.id DESC', [$id]);
$incassato = 0; $da_incassare = 0; $fatturato_anno = 0;
foreach ($fatt as $f) {
    if ($f['annullata']) continue;
    $incassato += $f['pagato'];
    $da_incassare += max(0, $f['totale'] - $f['pagato']);
    if (substr($f['data'], 0, 4) === date('Y')) $fatturato_anno += $f['imponibile'];
}
$contratti = all('SELECT * FROM contratti WHERE cliente_id=? ORDER BY stato=\'attivo\' DESC, data_inizio DESC', [$id]);
$progetti = all("SELECT p.*, (SELECT COUNT(*) FROM task WHERE progetto_id=p.id) tot, (SELECT COUNT(*) FROM task WHERE progetto_id=p.id AND stato='fatto') fatti FROM progetti p WHERE p.cliente_id=? ORDER BY FIELD(p.stato,'in_corso','da_iniziare','in_pausa','completato','annullato'), p.scadenza", [$id]);
$prev = all('SELECT * FROM preventivi WHERE cliente_id=? ORDER BY data DESC, id DESC', [$id]);
$task_aperti = all("SELECT t.*, p.titolo AS progetto FROM task t LEFT JOIN progetti p ON p.id=t.progetto_id WHERE t.cliente_id=? AND t.stato<>'fatto' ORDER BY t.scadenza IS NULL, t.scadenza, t.ordine", [$id]);
$eventi = all('SELECT * FROM eventi WHERE cliente_id=? AND data >= CURDATE() ORDER BY data, ora_inizio LIMIT 5', [$id]);
$mrr = 0; foreach ($contratti as $k) if ($k['stato'] === 'attivo' && PERIODO_MESI[$k['periodicita']] > 0) $mrr += $k['importo'] / PERIODO_MESI[$k['periodicita']];

$tabs = [
    'panoramica' => 'Panoramica',
    'commerciale' => 'Preventivi <em>' . count($prev) . '</em>',
    'lavoro' => 'Progetti e task <em>' . count($progetti) . '</em>',
    'soldi' => 'Fatture e gestioni <em>' . (count($fatt) + count($contratti)) . '</em>',
    'file' => 'File',
];

layout_start($nome, 'clienti');
$sub = badge($c['stato'], STATI_CLIENTE[$c['stato']]);
if ($c['nome_breve'] && $c['nome_breve'] !== $c['ragione_sociale']) $sub .= ' <span class="muted">' . h($c['ragione_sociale']) . '</span>';
if ($c['settore']) $sub .= ' <span class="muted">· ' . h($c['settore']) . '</span>';
page_head($nome,
    '<a class="btn" href="' . url('cliente', ['id' => $id, 'modifica' => 1]) . '">' . icon('edit') . ' Modifica</a>
     <details class="menu"><summary class="btn btn-primary">' . icon('plus') . ' Aggiungi</summary><div class="menu-list">
       <a href="' . url('preventivo', ['nuovo' => 1, 'cliente_id' => $id]) . '">Preventivo</a>
       <a href="' . url('progetto', ['modifica' => 1, 'cliente_id' => $id]) . '">Progetto</a>
       <a href="' . url('fattura', ['nuova' => 1, 'cliente_id' => $id]) . '">Fattura</a>
       <a href="' . url('contratto', ['modifica' => 1, 'cliente_id' => $id]) . '">Gestione</a>
       <a href="' . url('calendario', ['nuovo' => 1, 'cliente_id' => $id]) . '">Appuntamento</a>
     </div></details>', $sub);
?>
<div class="stats stats-4">
  <div class="stat"><span>Fatturato <?= date('Y') ?></span><b><?= money($fatturato_anno, false) ?></b><small>imponibile</small></div>
  <div class="stat"><span>Da incassare</span><b class="<?= $da_incassare > 0 ? 'accent' : '' ?>"><?= money($da_incassare) ?></b><small>incassato in tutto <?= money($incassato, false) ?></small></div>
  <div class="stat"><span>Canoni attivi</span><b><?= money($mrr, false) ?></b><small>al mese</small></div>
  <div class="stat"><span>Cliente dal</span><b><?= d_short($c['created_at']) ?> <?= date('Y', strtotime($c['created_at'])) ?></b><small><?= count($progetti) ?> progetti</small></div>
</div>

<div class="tabs tabs-line">
  <?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= url('cliente', ['id' => $id, 'tab' => $k]) ?>"><?= $l ?></a><?php endforeach; ?>
</div>

<?php if ($tab === 'panoramica'):
  $note = all('SELECT * FROM attivita WHERE cliente_id=? ORDER BY created_at DESC, id DESC LIMIT 60', [$id]); ?>
<div class="cols">
  <div class="col-main">
    <section class="card">
      <h2>Storico e note</h2>
      <form method="post" action="<?= url('azioni') ?>" class="note-form">
        <?= csrf() ?><input type="hidden" name="azione" value="nota"><input type="hidden" name="cliente_id" value="<?= $id ?>">
        <textarea name="testo" rows="2" placeholder="Com'è andata la call? Cosa vi siete detti?" required></textarea>
        <div class="row"><select name="tipo"><?= options(TIPI_NOTA, 'nota') ?></select><button class="btn btn-sm btn-primary">Aggiungi</button></div>
      </form>
      <div class="timeline">
        <?php foreach ($note as $n): ?>
          <div class="tl-item tl-<?= h($n['tipo']) ?>">
            <div class="tl-meta"><?= $n['tipo'] !== 'sistema' ? '<b>' . h(TIPI_NOTA[$n['tipo']] ?? $n['tipo']) . '</b> · ' : '' ?><?= d($n['created_at']) ?> <?= substr($n['created_at'], 11, 5) ?>
              <?php if ($n['tipo'] !== 'sistema'): ?>
              <form method="post" action="<?= url('azioni') ?>" class="inline" data-conferma="Eliminare la nota?"><?= csrf() ?><input type="hidden" name="azione" value="elimina_nota"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="link-btn">elimina</button></form>
              <?php endif; ?>
            </div>
            <div class="tl-text"><?= nl2br(h($n['testo'])) ?><?php if ($n['link']): ?> <a href="<?= h($n['link']) ?>">apri →</a><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$note): ?><p class="muted small">Ancora niente. Le note che scrivi e le cose che succedono (preventivi, fatture, pagamenti) finiscono qui.</p><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-side">
    <section class="card">
      <h2>Contatti</h2>
      <ul class="info">
        <?php if ($c['email']): ?><li><?= icon('mail') ?><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></li><?php endif; ?>
        <?php if ($c['telefono']): ?><li><?= icon('phone') ?><a href="tel:<?= h(preg_replace('/\s+/', '', $c['telefono'])) ?>"><?= h($c['telefono']) ?></a></li><?php endif; ?>
        <?php if ($c['sito']): ?><li><?= icon('link') ?><a href="<?= h($c['sito']) ?>" target="_blank" rel="noopener"><?= h(preg_replace('#^https?://(www\.)?#', '', $c['sito'])) ?></a></li><?php endif; ?>
        <?php if ($c['instagram']): ?><li><?= icon('link') ?><a href="https://instagram.com/<?= h(ltrim($c['instagram'], '@')) ?>" target="_blank" rel="noopener">@<?= h(ltrim($c['instagram'], '@')) ?></a></li><?php endif; ?>
      </ul>
      <h3>Referenti</h3>
      <?php foreach ($contatti as $p): ?>
        <details class="person">
          <summary><b><?= h($p['nome']) ?></b><?= $p['principale'] ? ' <span class="badge">principale</span>' : '' ?><?php if ($p['ruolo']): ?><br><span class="muted small"><?= h($p['ruolo']) ?></span><?php endif; ?>
            <div class="small"><?php if ($p['email']): ?><a href="mailto:<?= h($p['email']) ?>"><?= h($p['email']) ?></a> <?php endif; ?><?php if ($p['telefono']): ?><a href="tel:<?= h(preg_replace('/\s+/', '', $p['telefono'])) ?>"><?= h($p['telefono']) ?></a><?php endif; ?></div>
            <?php if ($p['note']): ?><div class="muted small"><?= h($p['note']) ?></div><?php endif; ?>
          </summary>
          <form method="post" class="mini-form">
            <?= csrf() ?><input type="hidden" name="azione" value="contatto"><input type="hidden" name="contatto_id" value="<?= $p['id'] ?>">
            <input name="nome" value="<?= h($p['nome']) ?>" placeholder="Nome" required><input name="ruolo" value="<?= h($p['ruolo']) ?>" placeholder="Ruolo">
            <input name="email" type="email" value="<?= h($p['email']) ?>" placeholder="Email"><input name="telefono" value="<?= h($p['telefono']) ?>" placeholder="Telefono">
            <input name="note" value="<?= h($p['note']) ?>" placeholder="Note">
            <label class="check"><input type="checkbox" name="principale" value="1" <?= $p['principale'] ? 'checked' : '' ?>> Principale</label>
            <div class="row"><button class="btn btn-sm">Salva</button>
              <button class="link-btn danger" name="azione" value="elimina_contatto" data-conferma="Eliminare il referente?">Elimina</button></div>
          </form>
        </details>
      <?php endforeach; ?>
      <details class="add">
        <summary class="link-btn"><?= icon('plus') ?> Aggiungi referente</summary>
        <form method="post" class="mini-form">
          <?= csrf() ?><input type="hidden" name="azione" value="contatto">
          <input name="nome" placeholder="Nome" required><input name="ruolo" placeholder="Ruolo">
          <input name="email" type="email" placeholder="Email"><input name="telefono" placeholder="Telefono">
          <label class="check"><input type="checkbox" name="principale" value="1" <?= $contatti ? '' : 'checked' ?>> Principale</label>
          <button class="btn btn-sm">Aggiungi</button>
        </form>
      </details>
    </section>

    <?php if ($task_aperti || $eventi): ?>
    <section class="card">
      <h2>In arrivo</h2>
      <?php foreach ($eventi as $e): ?>
        <div class="mini-item"><?= icon('calendar') ?> <span><b><?= d_short($e['data']) ?><?= $e['ora_inizio'] ? ' ' . t($e['ora_inizio']) : '' ?></b> <?= h($e['titolo']) ?></span></div>
      <?php endforeach; ?>
      <?php foreach (array_slice($task_aperti, 0, 8) as $t): ?>
        <div class="mini-item"><?= icon('check') ?> <span><?= h($t['titolo']) ?> <?php if ($t['scadenza']): ?><span class="due <?= due_class($t['scadenza']) ?>"><?= d_short($t['scadenza']) ?></span><?php endif; ?></span></div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2>Fatturazione</h2>
      <dl class="kv">
        <dt>Ragione sociale</dt><dd><?= h($c['ragione_sociale']) ?></dd>
        <?php if ($c['piva']): ?><dt>P.IVA</dt><dd class="copyable"><?= h($c['piva']) ?></dd><?php endif; ?>
        <?php if ($c['codice_fiscale']): ?><dt>Cod. fiscale</dt><dd class="copyable"><?= h($c['codice_fiscale']) ?></dd><?php endif; ?>
        <?php if ($c['codice_sdi']): ?><dt>SDI</dt><dd class="copyable"><?= h($c['codice_sdi']) ?></dd><?php endif; ?>
        <?php if ($c['pec']): ?><dt>PEC</dt><dd class="copyable"><?= h($c['pec']) ?></dd><?php endif; ?>
        <?php if ($c['indirizzo']): ?><dt>Sede</dt><dd><?= h($c['indirizzo']) ?><br><?= h(trim($c['cap'] . ' ' . $c['citta'] . ($c['provincia'] ? ' (' . $c['provincia'] . ')' : ''))) ?></dd><?php endif; ?>
        <?php if ($c['fonte']): ?><dt>Arrivato da</dt><dd><?= h($c['fonte']) ?></dd><?php endif; ?>
      </dl>
      <?php if (!$c['piva'] && !$c['codice_fiscale']): ?><p class="muted small">Mancano i dati fiscali. <a href="<?= url('cliente', ['id' => $id, 'modifica' => 1]) ?>">Aggiungili</a></p><?php endif; ?>
      <?php if ($c['note']): ?><h3>Note</h3><p class="small pre"><?= h($c['note']) ?></p><?php endif; ?>
    </section>
    <p class="small muted"><a href="<?= url('esporta', ['cosa' => 'cliente', 'id' => $id]) ?>"><?= icon('download') ?> Esporta tutti i dati di questo cliente</a><br>Utile se ti chiede quali dati conservi su di lui. Per cancellarli tutti: Modifica → Elimina cliente.</p>
  </div>
</div>

<?php elseif ($tab === 'commerciale'): ?>
<section class="card">
  <div class="card-head"><h2>Preventivi</h2><a class="btn btn-sm" href="<?= url('preventivo', ['nuovo' => 1, 'cliente_id' => $id]) ?>"><?= icon('plus') ?> Nuovo</a></div>
  <?php if (!$prev): ?><p class="muted">Nessun preventivo.</p><?php else: ?>
  <table class="table"><thead><tr><th>N.</th><th>Oggetto</th><th>Data</th><th>Stato</th><th class="num">Imponibile</th></tr></thead><tbody>
  <?php foreach ($prev as $p): $tot = prev_totali((int)$p['id']); ?>
    <tr class="row-link" data-href="<?= url('preventivo', ['id' => $p['id']]) ?>"><td><?= h($p['numero']) ?></td><td><a href="<?= url('preventivo', ['id' => $p['id']]) ?>"><?= h($p['oggetto']) ?></a></td><td><?= d($p['data']) ?></td>
      <td><?= badge($p['stato'], STATI_PREV[$p['stato']]) ?></td><td class="num"><?= money($tot['imponibile']) ?></td></tr>
  <?php endforeach; ?></tbody></table><?php endif; ?>
</section>

<?php elseif ($tab === 'lavoro'): ?>
<section class="card">
  <div class="card-head"><h2>Progetti</h2><a class="btn btn-sm" href="<?= url('progetto', ['modifica' => 1, 'cliente_id' => $id]) ?>"><?= icon('plus') ?> Nuovo</a></div>
  <?php if (!$progetti): ?><p class="muted">Nessun progetto.</p><?php else: ?>
  <div class="proj-grid">
  <?php foreach ($progetti as $p): $pct = $p['tot'] ? round($p['fatti'] / $p['tot'] * 100) : 0; ?>
    <a class="proj-card" href="<?= url('progetto', ['id' => $p['id']]) ?>">
      <div class="row-between"><b><?= h($p['titolo']) ?></b><?= badge($p['stato'], STATI_PROG[$p['stato']]) ?></div>
      <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
      <div class="muted small"><?= $p['fatti'] ?>/<?= $p['tot'] ?> task<?php if ($p['scadenza']): ?> · consegna <span class="due <?= due_class($p['scadenza'], in_array($p['stato'], ['completato','annullato'])) ?>"><?= d_short($p['scadenza']) ?></span><?php endif; ?></div>
    </a>
  <?php endforeach; ?></div><?php endif; ?>
</section>
<section class="card">
  <h2>Task aperti</h2>
  <?php $task_list = $task_aperti; $quick = ['cliente_id' => $id]; require APP_ROOT . '/pages/_task_list.php'; ?>
</section>

<?php elseif ($tab === 'soldi'): ?>
<section class="card">
  <div class="card-head"><h2>Gestioni e servizi ricorrenti</h2><a class="btn btn-sm" href="<?= url('contratto', ['modifica' => 1, 'cliente_id' => $id]) ?>"><?= icon('plus') ?> Nuovo</a></div>
  <?php if (!$contratti): ?><p class="muted">Nessuna gestione.</p><?php else: ?>
  <table class="table"><thead><tr><th>Gestione</th><th class="num">Importo</th><th>Periodo</th><th>Scadenza</th><th>Stato</th></tr></thead><tbody>
  <?php foreach ($contratti as $k): $sc = contr_scadenza($k); ?>
    <tr class="row-link" data-href="<?= url('contratto', ['id' => $k['id']]) ?>"><td><a href="<?= url('contratto', ['id' => $k['id']]) ?>"><?= h($k['titolo']) ?></a></td>
      <td class="num"><?= money($k['importo']) ?></td><td><?= PERIODICITA[$k['periodicita']] ?></td>
      <td><?= $sc ? '<span class="due ' . ($k['stato'] === 'attivo' ? due_class($sc) : '') . '">' . d($sc) . '</span>' . ($k['rinnovo_automatico'] ? ' <span class="muted small">rinnovo auto</span>' : '') : '<span class="muted">—</span>' ?></td>
      <td><?= badge($k['stato'], STATI_CONTR[$k['stato']]) ?></td></tr>
  <?php endforeach; ?></tbody></table><?php endif; ?>
</section>
<section class="card">
  <div class="card-head"><h2>Fatture</h2><a class="btn btn-sm" href="<?= url('fattura', ['nuova' => 1, 'cliente_id' => $id]) ?>"><?= icon('plus') ?> Nuova</a></div>
  <?php if (!$fatt): ?><p class="muted">Nessuna fattura.</p><?php else: ?>
  <table class="table"><thead><tr><th>N.</th><th>Data</th><th class="hide-sm">Descrizione</th><th class="num">Totale</th><th class="num">Residuo</th><th>Stato</th></tr></thead><tbody>
  <?php foreach ($fatt as $f): [$sk, $sl] = fatt_stato($f); ?>
    <tr class="row-link" data-href="<?= url('fattura', ['id' => $f['id']]) ?>"><td><a href="<?= url('fattura', ['id' => $f['id']]) ?>"><?= h($f['numero']) ?></a></td><td><?= d($f['data']) ?></td>
      <td class="hide-sm"><?= h($f['descrizione']) ?></td><td class="num"><?= money($f['totale']) ?></td>
      <td class="num"><?= $f['annullata'] ? '—' : money(max(0, $f['totale'] - $f['pagato'])) ?></td><td><?= badge($sk, $sl) ?></td></tr>
  <?php endforeach; ?></tbody></table><?php endif; ?>
</section>

<?php elseif ($tab === 'file'): ?>
<section class="card">
  <h2>File del cliente</h2>
  <p class="muted small">Contratti firmati, brief, loghi, documenti: tutto in un posto.</p>
  <?= allegati_box('cliente', $id) ?>
</section>
<?php endif;
layout_end();
