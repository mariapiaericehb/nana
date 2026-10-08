<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// Azioni comuni (form POST da più pagine)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
$a = post('azione');

switch ($a) {
    case 'bulk':
        $tipo = post('tipo');
        $op = post('op');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($x) => $x > 0)));
        $tab = ['clienti' => 'clienti', 'trattative' => 'trattative', 'preventivi' => 'preventivi', 'fatture' => 'fatture',
                'contratti' => 'contratti', 'progetti' => 'progetti', 'task' => 'task', 'eventi' => 'eventi'][$tipo] ?? null;
        if (!$tab || !$ids) { flash('Non hai selezionato niente.', 'err'); back(); }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $n = count($ids);
        // file collegati da cancellare insieme ai record
        $togli_file = function (string $entita, array $ids) {
            if (!$ids) return;
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (all("SELECT * FROM allegati WHERE entita=? AND entita_id IN ($in)", [$entita, ...$ids]) as $f) {
                @unlink(upload_dir() . '/' . basename($f['file']));
                delete_row('allegati', (int)$f['id']);
            }
        };
        $sub = fn(string $t) => array_map('intval', array_column(all("SELECT id FROM $t WHERE cliente_id IN ($in)", $ids), 'id'));
        db()->beginTransaction();
        switch ("$tipo:$op") {
            case 'clienti:ex':
                q("UPDATE clienti SET stato='ex' WHERE id IN ($in)", $ids);
                foreach ($ids as $i) log_act($i, 'Stato cambiato in Ex cliente');
                $msg = pl($n, 'cliente segnato', 'clienti segnati') . ' come ex.';
                break;
            case 'clienti:elimina':
                foreach (['contratto' => 'contratti', 'progetto' => 'progetti', 'preventivo' => 'preventivi', 'fattura' => 'fatture'] as $e => $t) $togli_file($e, $sub($t));
                $togli_file('cliente', $ids);
                q("DELETE FROM clienti WHERE id IN ($in)", $ids);
                $msg = pl($n, 'cliente eliminato', 'clienti eliminati') . ' con tutti i dati collegati.';
                break;
            case 'trattative:persa':
                q("UPDATE trattative SET fase='persa', probabilita=0 WHERE id IN ($in)", $ids);
                $msg = pl($n, 'trattativa segnata come persa', 'trattative segnate come perse') . '.';
                break;
            case 'preventivi:rifiutato':
                q("UPDATE preventivi SET stato='rifiutato' WHERE id IN ($in)", $ids);
                $msg = pl($n, 'preventivo segnato come rifiutato', 'preventivi segnati come rifiutati') . '.';
                break;
            case 'fatture:pagata':
                $c = 0;
                foreach (all('SELECT ' . FATT_COLS . " FROM fatture f WHERE f.id IN ($in) AND f.annullata=0", $ids) as $f) {
                    $res = round($f['totale'] - $f['pagato'], 2);
                    if ($res <= 0) continue;
                    insert('pagamenti', ['fattura_id' => $f['id'], 'data' => date('Y-m-d'), 'importo' => $res, 'metodo' => 'Bonifico']);
                    log_act((int)$f['cliente_id'], 'Incassati ' . money($res) . ' sulla fattura ' . $f['numero'] . ' (saldata)', url('fattura', ['id' => $f['id']]));
                    $c++;
                }
                $msg = $c ? pl($c, 'fattura segnata come pagata', 'fatture segnate come pagate') . ' oggi.' : 'Le fatture scelte erano già pagate.';
                break;
            case 'contratti:concluso':
                q("UPDATE contratti SET stato='concluso' WHERE id IN ($in)", $ids);
                $msg = pl($n, 'gestione segnata come conclusa', 'gestioni segnate come concluse') . '.';
                break;
            case 'progetti:completato':
                q("UPDATE progetti SET stato='completato' WHERE id IN ($in)", $ids);
                $msg = pl($n, 'progetto segnato come completato', 'progetti segnati come completati') . '.';
                break;
            case 'task:fatto':
                q("UPDATE task SET stato='fatto', completato_il=COALESCE(completato_il, NOW()) WHERE id IN ($in)", $ids);
                $msg = pl($n, 'task segnato come fatto', 'task segnati come fatti') . '.';
                break;
            case 'task:sposta':
                $dt = date_or_null(post('nuova_data'));
                q("UPDATE task SET scadenza=? WHERE id IN ($in)", [$dt, ...$ids]);
                $msg = pl($n, 'task spostato', 'task spostati') . ' ' . ($dt ? 'al ' . d($dt) : 'senza data') . '.';
                break;
            case 'trattative:elimina': case 'preventivi:elimina': case 'fatture:elimina':
            case 'contratti:elimina': case 'progetti:elimina': case 'task:elimina': case 'eventi:elimina':
                $ent = ['preventivi' => 'preventivo', 'fatture' => 'fattura', 'contratti' => 'contratto', 'progetti' => 'progetto'][$tipo] ?? null;
                if ($ent) $togli_file($ent, $ids);
                q("DELETE FROM $tab WHERE id IN ($in)", $ids);
                $msg = pl($n, ...[
                    'trattative' => ['trattativa eliminata', 'trattative eliminate'], 'preventivi' => ['preventivo eliminato', 'preventivi eliminati'],
                    'fatture' => ['fattura eliminata', 'fatture eliminate'], 'contratti' => ['gestione eliminata', 'gestioni eliminate'],
                    'progetti' => ['progetto eliminato', 'progetti eliminati'], 'task' => ['task eliminato', 'task eliminati'],
                    'eventi' => ['appuntamento eliminato', 'appuntamenti eliminati'],
                ][$tipo]) . '.';
                break;
            default:
                db()->rollBack();
                flash('Azione non valida.', 'err');
                back();
        }
        db()->commit();
        flash($msg);
        back();

    case 'importa_testo':
        $titolo = post('titolo');
        $righe = leggi_righe_date((string)post('righe'));
        if ($titolo === '' || !$righe) { flash($titolo === '' ? 'Scrivi un titolo.' : 'Non ho trovato date nel testo incollato.', 'err'); back(); }
        [$n, $s] = importa_eventi($righe, $titolo, pick(post('tipo'), TIPI_EVENTO, 'appuntamento'), id_or_null(post('cliente_id')), nul(post('luogo')));
        flash(pl($n, 'appuntamento aggiunto', 'appuntamenti aggiunti') . ($s ? ' · ' . pl($s, 'era già presente ed è stato saltato', 'erano già presenti e sono stati saltati') : '') . '.');
        redirect(url('calendario', ['m' => substr(min(array_column($righe, 0)), 0, 7)]));

    case 'importa_pacchetto':
        $nome = basename((string)post('file'));
        $p = pacchetti_da_importare()[$nome] ?? null;
        if (!$p) back();
        if (post('ignora')) { set_setting('importato_' . $nome, 'ignorato ' . date('c')); flash('Ok, non lo importo.'); back(); }
        [$n, $s] = importa_eventi($p['righe'], $p['titolo'], pick($p['tipo'] ?? '', TIPI_EVENTO, 'appuntamento'));
        set_setting('importato_' . $nome, date('c'));
        flash('"' . $p['titolo'] . '": ' . pl($n, 'appuntamento aggiunto', 'appuntamenti aggiunti') . ($s ? ', ' . pl($s, 'già presente', 'già presenti') . ' e quindi saltati' : '') . '. Gli altri appuntamenti non sono stati toccati.');
        redirect(url('calendario', ['m' => substr(min(array_column($p['righe'], 0)), 0, 7)]));

    case 'carica_allegato':
        $ent = pick(post('entita'), ['cliente' => 1, 'contratto' => 1, 'progetto' => 1, 'preventivo' => 1, 'fattura' => 1], '');
        if (!$ent) back();
        $err = salva_allegato($ent, (int)post('entita_id'), $_FILES['file'] ?? []);
        flash($err ?? 'File caricato.', $err ? 'err' : 'ok');
        back();

    case 'elimina_allegato':
        $f = one('SELECT * FROM allegati WHERE id=?', [(int)post('id')]);
        if ($f) {
            @unlink(upload_dir() . '/' . basename($f['file']));
            delete_row('allegati', (int)$f['id']);
            flash('File eliminato.');
        }
        back();

    case 'nota':
        $cid = id_or_null(post('cliente_id'));
        $testo = post('testo');
        if ($cid && $testo !== '') {
            log_act($cid, $testo, null, pick(post('tipo'), TIPI_NOTA, 'nota'));
            flash('Nota aggiunta.');
        }
        back();

    case 'elimina_nota':
        q("DELETE FROM attivita WHERE id=? AND tipo<>'sistema'", [(int)post('id')]);
        back();

    case 'task_rapido':
        $titolo = post('titolo');
        if ($titolo !== '') {
            $pid = id_or_null(post('progetto_id'));
            [$cid, $pers] = task_chi((string)post('cliente_id'));
            if ($pid && !$cid) $cid = id_or_null(val('SELECT cliente_id FROM progetti WHERE id=?', [$pid]));
            insert('task', [
                'titolo' => $titolo, 'progetto_id' => $pid, 'cliente_id' => $cid, 'personale' => $pid ? 0 : $pers,
                'scadenza' => date_or_null(post('scadenza')), 'priorita' => pick(post('priorita'), PRIORITA, 'media'),
                'stato' => pick(post('stato'), STATI_TASK, 'da_fare'),
                'ordine' => (int)val('SELECT COALESCE(MAX(ordine),0)+1 FROM task'),
            ]);
            flash('Task aggiunto.');
        }
        back();

    case 'task_salva':
        $id = (int)post('id');
        $stato = pick(post('stato'), STATI_TASK, 'da_fare');
        $pid = id_or_null(post('progetto_id'));
        [$cid, $pers] = task_chi((string)post('cliente_id'));
        if ($pid) $pers = 0;
        if ($pid) $cid = id_or_null(val('SELECT cliente_id FROM progetti WHERE id=?', [$pid])) ?? $cid;
        $data = [
            'titolo' => post('titolo') ?: 'Senza titolo', 'descrizione' => nul(post('descrizione')),
            'progetto_id' => $pid, 'cliente_id' => $cid, 'personale' => $pers, 'stato' => $stato,
            'priorita' => pick(post('priorita'), PRIORITA, 'media'),
            'scadenza' => date_or_null(post('scadenza')), 'ora' => time_or_null(post('ora')),
        ];
        $old = $id ? one('SELECT stato FROM task WHERE id=?', [$id]) : null;
        if ($stato === 'fatto' && ($old['stato'] ?? '') !== 'fatto') $data['completato_il'] = date('Y-m-d H:i:s');
        if ($stato !== 'fatto') $data['completato_il'] = null;
        if ($id) update('task', $id, $data); else insert('task', $data + ['ordine' => (int)val('SELECT COALESCE(MAX(ordine),0)+1 FROM task')]);
        flash('Task salvato.');
        back();

    case 'task_elimina':
        delete_row('task', (int)post('id'));
        flash('Task eliminato.');
        back();

    case 'evento_salva':
        $id = (int)post('id');
        $data = [
            'titolo' => post('titolo') ?: 'Appuntamento', 'tipo' => pick(post('tipo'), TIPI_EVENTO, 'appuntamento'),
            'cliente_id' => id_or_null(post('cliente_id')), 'data' => date_or_null(post('data')) ?? date('Y-m-d'),
            'ora_inizio' => time_or_null(post('ora_inizio')), 'ora_fine' => time_or_null(post('ora_fine')),
            'luogo' => nul(post('luogo')), 'note' => nul(post('note')),
            'ripeti' => pick(post('ripeti'), RIPETI, 'no'), 'ripeti_fino' => date_or_null(post('ripeti_fino')),
        ];
        if ($data['tipo'] === 'personale') $data['cliente_id'] = null;
        if ($id) update('eventi', $id, $data);
        else {
            insert('eventi', $data);
            if ($data['cliente_id']) log_act($data['cliente_id'], TIPI_EVENTO[$data['tipo']] . ' fissato per il ' . d($data['data']) . ($data['ora_inizio'] ? ' alle ' . $data['ora_inizio'] : '') . ': ' . $data['titolo']);
        }
        flash('Salvato in calendario.');
        back();

    case 'evento_elimina':
        delete_row('eventi', (int)post('id'));
        flash('Eliminato dal calendario.');
        back();
}
back();
