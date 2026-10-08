<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// Dati di esempio (tutti i clienti hanno fonte = "Dati di esempio" e si cancellano da Impostazioni)
function demo_data(PDO $pdo): void {
    $d = fn(string $mod) => date('Y-m-d', strtotime($mod));
    $ins = function (string $t, array $data) use ($pdo): int {
        $c = array_keys($data);
        $pdo->prepare("INSERT INTO `$t` (`" . implode('`,`', $c) . "`) VALUES (" . implode(',', array_fill(0, count($c), '?')) . ")")->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    };
    $F = 'Dati di esempio';
    $cli = [];
    $cli['forno'] = $ins('clienti', ['stato' => 'cliente', 'ragione_sociale' => 'Antico Forno Russo S.r.l.', 'nome_breve' => 'Antico Forno', 'settore' => 'Food', 'fonte' => $F, 'piva' => '01234560871', 'codice_sdi' => 'M5UXCR1', 'indirizzo' => 'Via Etnea 120', 'cap' => '95131', 'citta' => 'Catania', 'provincia' => 'CT', 'email' => 'info@anticoforno.example', 'telefono' => '095 000 1122', 'instagram' => '@anticoforno', 'colore' => '#e07b2a', 'created_at' => $d('-14 months')]);
    $cli['studio'] = $ins('clienti', ['stato' => 'cliente', 'ragione_sociale' => 'Studio Dentistico Leone', 'nome_breve' => 'Studio Leone', 'settore' => 'Salute', 'fonte' => $F, 'piva' => '04567890872', 'citta' => 'Acireale', 'provincia' => 'CT', 'email' => 'segreteria@studioleone.example', 'telefono' => '095 000 3344', 'colore' => '#2a8fe0', 'created_at' => $d('-7 months')]);
    $cli['moda'] = $ins('clienti', ['stato' => 'cliente', 'ragione_sociale' => 'Atelier Marea di Giulia Costa', 'nome_breve' => 'Atelier Marea', 'settore' => 'Moda', 'fonte' => $F, 'piva' => '05678901234', 'citta' => 'Siracusa', 'provincia' => 'SR', 'email' => 'giulia@ateliermarea.example', 'instagram' => '@ateliermarea', 'colore' => '#b04ac9', 'created_at' => $d('-4 months')]);
    $cli['hotel'] = $ins('clienti', ['stato' => 'potenziale', 'ragione_sociale' => 'Hotel Faraglioni', 'settore' => 'Turismo', 'fonte' => $F, 'citta' => 'Aci Trezza', 'provincia' => 'CT', 'email' => 'direzione@hotelfaraglioni.example', 'colore' => '#1fa58a', 'created_at' => $d('-3 weeks')]);
    $cli['palestra'] = $ins('clienti', ['stato' => 'potenziale', 'ragione_sociale' => 'Kinetic Gym ASD', 'nome_breve' => 'Kinetic Gym', 'settore' => 'Sport', 'fonte' => $F, 'citta' => 'Catania', 'provincia' => 'CT', 'created_at' => $d('-10 days')]);

    foreach ([
        ['forno', 'Salvo Russo', 'Titolare', 'salvo@anticoforno.example', '333 000 1111', 1],
        ['forno', 'Marta Russo', 'Amministrazione', 'amministrazione@anticoforno.example', '', 0],
        ['studio', 'Dott.ssa Elena Leone', 'Titolare', 'elena@studioleone.example', '347 000 2222', 1],
        ['moda', 'Giulia Costa', 'Fondatrice', 'giulia@ateliermarea.example', '340 000 3333', 1],
        ['hotel', 'Andrea Grasso', 'Direttore', 'direzione@hotelfaraglioni.example', '320 000 4444', 1],
        ['palestra', 'Davide Pappalardo', 'Presidente', '', '329 000 5555', 1],
    ] as [$c, $n, $r, $e, $t, $p]) $ins('contatti', ['cliente_id' => $cli[$c], 'nome' => $n, 'ruolo' => $r, 'email' => $e ?: null, 'telefono' => $t ?: null, 'principale' => $p]);

    // Trattative
    $tr_hotel = $ins('trattative', ['cliente_id' => $cli['hotel'], 'titolo' => 'Social e foto per la stagione estiva', 'valore' => 4800, 'fase' => 'preventivo', 'probabilita' => 50, 'chiusura_prevista' => $d('+3 weeks'), 'prossimo_passo' => 'Richiamare per avere un riscontro sul preventivo', 'prossimo_passo_data' => $d('today')]);
    $ins('trattative', ['cliente_id' => $cli['palestra'], 'titolo' => 'Campagna iscrizioni gennaio', 'valore' => 1500, 'fase' => 'contattato', 'probabilita' => 25, 'prossimo_passo' => 'Mandare esempi di campagne fatte', 'prossimo_passo_data' => $d('+2 days')]);
    $ins('trattative', ['cliente_id' => $cli['moda'], 'titolo' => 'Shooting nuova collezione', 'valore' => 1800, 'fase' => 'negoziazione', 'probabilita' => 75, 'prossimo_passo' => 'Fissare data dello shooting', 'prossimo_passo_data' => $d('+5 days')]);
    $ins('trattative', ['cliente_id' => $cli['studio'], 'titolo' => 'Restyling sito web', 'valore' => 2500, 'fase' => 'nuova', 'probabilita' => 10]);
    $tr_forno = $ins('trattative', ['cliente_id' => $cli['forno'], 'titolo' => 'Gestione social annuale', 'valore' => 7200, 'fase' => 'vinta', 'probabilita' => 100]);

    // Preventivi
    $p1 = $ins('preventivi', ['cliente_id' => $cli['hotel'], 'trattativa_id' => $tr_hotel, 'numero' => date('Y') . '-001', 'data' => $d('-9 days'), 'oggetto' => 'Comunicazione social stagione estiva', 'stato' => 'inviato', 'iva_percentuale' => 22, 'introduzione' => "Gentile Andrea,\necco la proposta di cui abbiamo parlato per accompagnare l'hotel durante tutta la stagione.", 'condizioni' => "Pagamento: 50% all'accettazione, saldo alla consegna.\nI prezzi si intendono IVA esclusa."]);
    foreach ([["Strategia e piano editoriale\nAnalisi del pubblico, tono di voce, calendario dei contenuti.", 1, 900], ["Gestione Instagram e Facebook\n12 post e 20 storie al mese, da maggio a settembre.", 5, 600], ["Shooting fotografico in struttura\nUna giornata, 60 foto editate.", 1, 900]] as $i => [$desc, $q, $pz])
        $ins('preventivo_righe', ['preventivo_id' => $p1, 'descrizione' => $desc, 'quantita' => $q, 'prezzo' => $pz, 'ordine' => $i]);
    $p2 = $ins('preventivi', ['cliente_id' => $cli['forno'], 'trattativa_id' => $tr_forno, 'numero' => date('Y') . '-002', 'data' => $d('-2 months'), 'oggetto' => 'Gestione social annuale', 'stato' => 'accettato', 'iva_percentuale' => 22]);
    $ins('preventivo_righe', ['preventivo_id' => $p2, 'descrizione' => "Gestione Instagram e Facebook\n16 post al mese, storie, risposte ai messaggi.", 'quantita' => 12, 'prezzo' => 600, 'ordine' => 0]);

    // Contratti
    $k1 = $ins('contratti', ['cliente_id' => $cli['forno'], 'titolo' => 'Gestione social', 'importo' => 600, 'periodicita' => 'mensile', 'data_inizio' => date('Y-m-01', strtotime('-2 months')), 'data_fine' => date('Y-m-d', strtotime(date('Y-m-01', strtotime('-2 months')) . ' +1 year -1 day')), 'rinnovo_automatico' => 1, 'preavviso_giorni' => 30]);
    $k2 = $ins('contratti', ['cliente_id' => $cli['studio'], 'titolo' => 'Manutenzione sito e newsletter', 'importo' => 350, 'periodicita' => 'mensile', 'data_inizio' => date('Y-m-05', strtotime('-7 months')), 'data_fine' => $d('+20 days'), 'rinnovo_automatico' => 0, 'preavviso_giorni' => 30]);
    $k3 = $ins('contratti', ['cliente_id' => $cli['moda'], 'titolo' => 'Consulenza strategica', 'importo' => 900, 'periodicita' => 'trimestrale', 'data_inizio' => $d('-4 months'), 'rinnovo_automatico' => 0, 'preavviso_giorni' => 15]);

    // Fatture e pagamenti
    $n = 1;
    $fat = function ($cliente, $data, $imp, $desc, $pagata, $k = null, $p = null) use ($ins, &$n, $d) {
        $id = $ins('fatture', ['cliente_id' => $cliente, 'contratto_id' => $k, 'preventivo_id' => $p, 'numero' => (string)$n++, 'data' => $data, 'scadenza' => date('Y-m-d', strtotime("$data +30 days")), 'descrizione' => $desc, 'imponibile' => $imp, 'iva_percentuale' => 22]);
        if ($pagata === true) $ins('pagamenti', ['fattura_id' => $id, 'data' => date('Y-m-d', strtotime("$data +20 days")), 'importo' => round($imp * 1.22, 2), 'metodo' => 'Bonifico']);
        elseif (is_numeric($pagata)) $ins('pagamenti', ['fattura_id' => $id, 'data' => date('Y-m-d', strtotime("$data +25 days")), 'importo' => $pagata, 'metodo' => 'Bonifico']);
        return $id;
    };
    if ((int)date('n') > 3) {
        $fat($cli['moda'], date('Y-01-20'), 1200, 'Piano di comunicazione', true);
        $fat($cli['studio'], date('Y-02-10'), 1500, 'Nuovo sito web – saldo', true);
    }
    $fat($cli['studio'], $d('-65 days'), 350, 'Manutenzione sito e newsletter', true, $k2);
    $fat($cli['forno'], $d('-2 months'), 600, 'Gestione social', true, $k1, $p2);
    $fat($cli['studio'], $d('-35 days'), 350, 'Manutenzione sito e newsletter', false, $k2);
    $fat($cli['forno'], $d('-1 month'), 600, 'Gestione social', 300, $k1, $p2);
    $fat($cli['moda'], $d('-12 days'), 900, 'Consulenza strategica – trimestre', false, $k3);

    // Progetti e task
    $pr1 = $ins('progetti', ['cliente_id' => $cli['forno'], 'preventivo_id' => $p2, 'titolo' => 'Campagna Natale', 'descrizione' => "Obiettivo: ordini dei panettoni artigianali.\nCanali: Instagram e Facebook, più volantino in negozio.\nBudget ads: 400 € a carico del cliente.", 'stato' => 'in_corso', 'data_inizio' => $d('-1 week'), 'scadenza' => $d('+3 weeks'), 'budget' => 1500]);
    $pr2 = $ins('progetti', ['cliente_id' => $cli['moda'], 'titolo' => 'Lancio collezione autunno', 'stato' => 'da_iniziare', 'data_inizio' => $d('+1 week'), 'scadenza' => $d('+6 weeks')]);
    $ins('progetti', ['cliente_id' => $cli['studio'], 'titolo' => 'Nuovo sito web', 'stato' => 'completato', 'data_inizio' => $d('-6 months'), 'scadenza' => $d('-4 months')]);
    $i = 0;
    foreach ([
        [$pr1, $cli['forno'], 'Moodboard e concept', 'fatto', 'media', '-5 days'],
        [$pr1, $cli['forno'], 'Shooting prodotti in laboratorio', 'in_corso', 'alta', 'today'],
        [$pr1, $cli['forno'], 'Testi dei post e delle storie', 'da_fare', 'media', '+3 days'],
        [$pr1, $cli['forno'], 'Impostare campagna Meta Ads', 'da_fare', 'media', '+8 days'],
        [$pr1, $cli['forno'], 'Approvazione del cliente sul volantino', 'in_attesa', 'media', '+2 days'],
        [$pr2, $cli['moda'], 'Brief con Giulia', 'da_fare', 'alta', '+1 day'],
        [$pr2, $cli['moda'], 'Location per lo shooting', 'da_fare', 'media', '+6 days'],
        [null, $cli['studio'], 'Inviare report mensile della newsletter', 'da_fare', 'media', '-1 day'],
        [null, $cli['hotel'], 'Preparare esempi di reel per hotel', 'da_fare', 'bassa', '+4 days'],
    ] as [$p, $c, $t, $s, $pr, $sc])
        $ins('task', ['progetto_id' => $p, 'cliente_id' => $c, 'titolo' => $t, 'stato' => $s, 'priorita' => $pr, 'scadenza' => $d($sc), 'ordine' => $i++, 'completato_il' => $s === 'fatto' ? date('Y-m-d H:i:s', strtotime('-4 days')) : null]);

    // Appuntamenti
    $ins('eventi', ['cliente_id' => $cli['moda'], 'titolo' => 'Call di allineamento', 'tipo' => 'call', 'data' => $d('+1 day'), 'ora_inizio' => '10:00', 'ora_fine' => '10:45', 'luogo' => 'Google Meet']);
    $ins('eventi', ['cliente_id' => $cli['hotel'], 'titolo' => 'Sopralluogo in hotel', 'tipo' => 'appuntamento', 'data' => $d('+4 days'), 'ora_inizio' => '15:30', 'luogo' => 'Hotel Faraglioni, Aci Trezza']);
    $ins('eventi', ['cliente_id' => $cli['forno'], 'titolo' => 'Shooting panettoni', 'tipo' => 'appuntamento', 'data' => $d('today'), 'ora_inizio' => '09:00', 'ora_fine' => '13:00', 'luogo' => 'Laboratorio, Via Etnea 120']);

    // Impegni personali (si cancellano a parte: sono senza cliente)
    $ins('eventi', ['titolo' => 'Palestra', 'tipo' => 'personale', 'data' => $d('monday this week'), 'ora_inizio' => '19:00', 'ora_fine' => '20:00', 'ripeti' => 'settimana', 'note' => '[esempio]']);
    $ins('eventi', ['titolo' => 'Compleanno di mamma', 'tipo' => 'personale', 'data' => date('Y-m-d', strtotime('+12 days -1 year')), 'ripeti' => 'anno', 'note' => '[esempio]']);
    $ins('eventi', ['titolo' => 'Dentista', 'tipo' => 'personale', 'data' => $d('+3 days'), 'ora_inizio' => '17:30', 'luogo' => 'Studio dott. Ferro', 'note' => '[esempio]']);
    $ins('task', ['titolo' => 'Rinnovare l\'assicurazione dell\'auto', 'personale' => 1, 'scadenza' => $d('+5 days'), 'descrizione' => '[esempio]']);
    $ins('task', ['titolo' => 'Prenotare il volo per Natale', 'personale' => 1, 'descrizione' => '[esempio]']);

    // Storico
    foreach ([
        [$cli['forno'], 'chiamata', 'Salvo vuole spingere molto i panettoni: chiede anche un volantino per il negozio.', '-6 days'],
        [$cli['hotel'], 'incontro', 'Primo incontro con Andrea. Vogliono più prenotazioni dirette e meno dipendenza da Booking. Budget indicativo 5.000 €.', '-12 days'],
        [$cli['studio'], 'nota', 'La gestione scade a breve: proporre il rinnovo insieme al restyling del sito.', '-2 days'],
    ] as [$c, $tipo, $testo, $q])
        $ins('attivita', ['cliente_id' => $c, 'tipo' => $tipo, 'testo' => $testo, 'created_at' => date('Y-m-d H:i:s', strtotime("$q 11:20"))]);
    foreach ($cli as $c) $ins('attivita', ['cliente_id' => $c, 'tipo' => 'sistema', 'testo' => 'Scheda creata (dati di esempio)', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 year'))]);
}
