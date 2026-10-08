<?php defined('APP_ROOT') || defined('INSTALL') || exit;
function layout_start(string $title, string $active = ''): void {
    $nome = app_nome();
    $logo = setting('logo');
    $nav = [
        ['dashboard', 'Oggi', 'home'],
        ['clienti', 'Clienti', 'users'],
        ['preventivi', 'Preventivi', 'doc'],
        ['fatture', 'Fatture', 'euro'],
        ['contratti', 'Gestioni', 'contract'],
        ['progetti', 'Progetti', 'folder'],
        ['task', 'Task', 'check'],
        ['calendario', 'Calendario', 'calendar'],
    ];
    // contatori per il menu
    $late = (int)val("SELECT COUNT(*) FROM task WHERE stato<>'fatto' AND scadenza < CURDATE()");
    $fatt_scad = (int)val("SELECT COUNT(*) FROM (SELECT " . FATT_COLS . " FROM fatture f WHERE f.annullata=0 AND f.scadenza < CURDATE()) x WHERE x.totale - x.pagato > 0.009");
    $counts = ['task' => $late, 'fatture' => $fatt_scad];
    ?><!doctype html>
<html lang="it"<?= tema_class() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?= h($title) ?> · <?= h($nome) ?></title>


<link rel="stylesheet" href="<?= asset("assets/style.css") ?>">
<style><?= theme_css() ?></style>
<link rel="icon" href="<?= asset('assets/nana-icona.svg') ?>">
</head>
<body>
<div class="app">
  <aside class="side" id="side">
    <a class="brand" href="index.php">
      <img class="brand-cat" src="<?= asset('assets/nana-gatto.svg') ?>" alt=""><span class="brand-word"><?= h($nome) ?></span>
    </a>
    <form class="side-search" action="index.php" method="get">
      <input type="hidden" name="p" value="cerca">
      <?= icon('search') ?><input type="search" name="q" placeholder="Cerca…" value="<?= h(get('q')) ?>">
    </form>
    <nav>
      <?php foreach ($nav as [$p, $label, $ic]): ?>
        <a href="<?= url($p) ?>" class="<?= $active === $p ? 'on' : '' ?>"><?= icon($ic) ?><span><?= $label ?></span>
          <?php if (!empty($counts[$p])): ?><em class="count"><?= $counts[$p] ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="<?= url('impostazioni') ?>" class="<?= $active === 'impostazioni' ? 'on' : '' ?>"><?= icon('settings') ?><span>Impostazioni</span></a>
      <a href="logout.php?t=<?= csrf_token() ?>"><?= icon('logout') ?><span>Esci</span></a>
    </div>
  </aside>
  <div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>
  <main class="main">
    <div class="topbar">
      <button class="icon-btn" onclick="document.body.classList.toggle('nav-open')" aria-label="Menu"><?= icon('menu') ?></button>
      <a class="brand" href="index.php"><img class="brand-cat" src="<?= asset('assets/nana-gatto.svg') ?>" alt=""><span class="brand-word"><?= h($nome) ?></span></a>
      <a class="icon-btn" href="<?= url('cerca') ?>" aria-label="Cerca"><?= icon('search') ?></a>
    </div>
    <?php foreach (flash() as [$type, $msg]): ?>
      <div class="flash flash-<?= h($type) ?>"><?= h($msg) ?></div>
    <?php endforeach; ?>
<?php
}

function layout_end(): void { ?>
  </main>
</div>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= asset("assets/app.js") ?>"></script>
</body>
</html>
<?php }

function page_head(string $title, string $actions = '', string $sub = ''): void { ?>
  <header class="page-head">
    <div>
      <h1><?= h($title) ?></h1>
      <?php if ($sub): ?><div class="sub"><?= $sub ?></div><?php endif; ?>
    </div>
    <?php if ($actions): ?><div class="actions"><?= $actions ?></div><?php endif; ?>
  </header>
<?php }

function empty_state(string $text, string $action = ''): string {
    return '<div class="empty"><p>' . h($text) . '</p>' . $action . '</div>';
}

// ---------- Selezione multipla ----------
// casella "seleziona tutti" nell'intestazione di una tabella
function sel_th(): string { return '<th class="sel-cell"><input type="checkbox" class="sel-all" autocomplete="off" aria-label="Seleziona tutti"></th>'; }
// casella di una riga
function sel_td(int $id): string { return '<td class="sel-cell"><input type="checkbox" class="sel" value="' . $id . '" autocomplete="off" aria-label="Seleziona"></td>'; }
// casella su una scheda (visibile in modalità "Seleziona")
function sel_box(int $id): string { return '<input type="checkbox" class="sel sel-card" value="' . $id . '" autocomplete="off" aria-label="Seleziona">'; }
function sel_toggle(string $cosa = 'più elementi'): string { return '<button type="button" class="btn sel-toggle" title="Scegli più elementi insieme per segnarli, spostarli o eliminarli">' . icon('check') . ' <span>Seleziona ' . h($cosa) . '</span></button>'; }
/**
 * Barra delle azioni di gruppo. $ops = ['op' => ['Etichetta', 'testo conferma con {n}', 'danger'|'' , 'extra html']]
 */
function bulk_bar(string $tipo, array $ops): string {
    $h = '<form method="post" action="' . url('azioni') . '" class="bulk-bar" hidden>' . csrf()
       . '<input type="hidden" name="azione" value="bulk"><input type="hidden" name="tipo" value="' . h($tipo) . '">'
       . '<input type="hidden" name="_back" value="' . h($_SERVER['REQUEST_URI'] ?? '') . '">'
       . '<label class="check bulk-all"><input type="checkbox" class="sel-all" autocomplete="off"> Tutti</label>'
       . '<span class="bulk-count"><b>0</b> selezionati</span><span class="bulk-ops">';
    foreach ($ops as $op => $o) {
        [$label, $conf, $style, $extra] = $o + [null, '', '', ''];
        $h .= $extra . '<button class="btn btn-sm ' . ($style === 'danger' ? 'btn-danger' : '') . '" name="op" value="' . h($op) . '"'
            . ($conf ? ' data-confirm="' . h($conf) . '"' : '') . ($style === 'danger' && in_array($tipo, ['clienti', 'fatture'], true) ? ' data-type-confirm="ELIMINA"' : '') . '>' . h($label) . '</button>';
    }
    return $h . '</span><button type="button" class="link-btn bulk-cancel">Annulla</button></form>';
}
