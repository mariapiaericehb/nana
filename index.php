<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$pages = ['dashboard','clienti','cliente','preventivi','preventivo','preventivo_stampa','fatture','fattura',
          'contratti','contratto','progetti','progetto','task','calendario','cerca','impostazioni','azioni','api','esporta','password'];
$p = get('p', 'dashboard');
if (!in_array($p, $pages, true)) { http_response_code(404); $p = 'dashboard'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') check_csrf();
require __DIR__ . '/pages/' . $p . '.php';
