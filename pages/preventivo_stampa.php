<?php defined('APP_ROOT') || defined('INSTALL') || exit;
$id = (int)get('id');
$p = one('SELECT * FROM preventivi WHERE id=?', [$id]);
if (!$p) redirect(url('preventivi'));
$c = one('SELECT * FROM clienti WHERE id=?', [$p['cliente_id']]);
$ref = one('SELECT * FROM contatti WHERE cliente_id=? ORDER BY principale DESC, id LIMIT 1', [$p['cliente_id']]);
$righe = all('SELECT * FROM preventivo_righe WHERE preventivo_id=? ORDER BY ordine', [$id]);
$tot = prev_totali($id);
$logo = setting('logo');
$az = [
    'nome' => setting('azienda_nome', 'HypeBang'), 'ragione' => setting('azienda_ragione_sociale'), 'indirizzo' => setting('azienda_indirizzo'),
    'piva' => setting('azienda_piva'), 'email' => setting('azienda_email'), 'tel' => setting('azienda_telefono'), 'sito' => setting('azienda_sito'),
    'iban' => setting('azienda_iban'),
];
$scade = date('Y-m-d', strtotime($p['data'] . ' +' . (int)$p['validita_giorni'] . ' days'));
$filename = 'Preventivo ' . $p['numero'] . ' - ' . ($c['nome_breve'] ?: $c['ragione_sociale']);
?><!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow, noarchive"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($filename) ?></title>
<link rel="stylesheet" href="<?= asset("assets/fonts.css") ?>">

<style>
<?= theme_css() ?>
:root{--ink:#32172e;--muted:#857580;--line:#ece3e0}
*{box-sizing:border-box}
body{margin:0;background:#efe6e3;font:13px/1.5 Inter,system-ui,sans-serif;color:var(--ink)}
.bar{position:sticky;top:0;display:flex;gap:8px;justify-content:center;padding:12px;background:#32172e}
.bar a,.bar button{font:600 13px Inter,sans-serif;padding:9px 16px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;background:#fff;color:#141414}
.bar button{background:var(--accent);color:var(--on-accent)}
.page{width:210mm;min-height:297mm;margin:24px auto;background:#fff;padding:18mm 18mm 16mm;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.12)}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding-bottom:10mm;border-bottom:3px solid var(--ink)}
.brand{font:800 30px/1 'Bricolage Grotesque',sans-serif;letter-spacing:-.02em}
.brand img{height:22mm;max-width:60mm;display:block}
.brand i{color:var(--accent-text);font-style:normal}
.from{text-align:right;font-size:11px;color:var(--muted);line-height:1.55}
.from b{color:var(--ink)}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:9mm 0 8mm}
.lbl{font-size:10px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:600;margin-bottom:4px}
.to b{font-size:15px}
.doc-title{font:800 34px/1.05 'Bricolage Grotesque',sans-serif;letter-spacing:-.025em;margin:0 0 3mm}
.doc-title span{color:var(--accent)}
.facts{display:flex;gap:22px;font-size:12px}
.facts div b{display:block;font-size:13px}
.intro{margin:0 0 7mm;font-size:13px;white-space:pre-line}
table{width:100%;border-collapse:collapse}
th{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);text-align:left;padding:0 0 3mm;border-bottom:1px solid var(--ink)}
td{padding:4mm 0;border-bottom:1px solid var(--line);vertical-align:top}
td.desc{white-space:pre-line;padding-right:8mm}
.desc::first-line{font-weight:600}
.n{text-align:right;white-space:nowrap;padding-left:5mm}
.sum{margin:7mm 0 0 auto;width:78mm}
.sum div{display:flex;justify-content:space-between;padding:1.6mm 0;font-size:12.5px}
.sum .grand{margin-top:2mm;padding:3.5mm 4mm;background:var(--ink);color:#fff;font:700 16px 'Bricolage Grotesque',sans-serif;border-radius:3px}
.cond{margin-top:10mm;display:grid;grid-template-columns:1.4fr 1fr;gap:10mm;font-size:11.5px}
.cond p{margin:0;white-space:pre-line;color:#3a3733}
.sign{margin-top:14mm;display:grid;grid-template-columns:1fr 1fr;gap:16mm;font-size:11px;color:var(--muted)}
.sign div{border-top:1px solid var(--ink);padding-top:2mm}
.foot{position:absolute;left:18mm;right:18mm;bottom:9mm;font-size:9.5px;color:var(--muted);display:flex;justify-content:space-between}
@media print{body{background:#fff}.bar{display:none}.page{margin:0;box-shadow:none;width:auto;min-height:auto;padding:0}.foot{position:static;margin-top:10mm}@page{size:A4;margin:15mm}}
@media (max-width:820px){.page{width:auto;margin:0;padding:24px 18px}.meta,.cond,.sign{grid-template-columns:1fr}.foot{position:static;margin-top:24px;flex-direction:column}}
</style></head>
<body>
<div class="bar"><a href="<?= url('preventivo', ['id' => $id]) ?>">← Torna al preventivo</a><button onclick="window.print()">Stampa / Salva come PDF</button></div>
<div class="page">
  <div class="top">
    <div class="brand"><?php if ($logo): ?><img src="<?= h($logo) ?>" alt="<?= h($az['nome']) ?>"><?php else: ?><?= h($az['nome']) ?><i>.</i><?php endif; ?></div>
    <div class="from">
      <b><?= h($az['ragione'] ?: $az['nome']) ?></b><br>
      <?php if ($az['indirizzo']): ?><?= nl2br(h($az['indirizzo'])) ?><br><?php endif; ?>
      <?php if ($az['piva']): ?>P.IVA <?= h($az['piva']) ?><br><?php endif; ?>
      <?= h(implode(' · ', array_filter([$az['email'], $az['tel']]))) ?><?php if ($az['sito']): ?><br><?= h($az['sito']) ?><?php endif; ?>
    </div>
  </div>
  <div class="meta">
    <div>
      <h1 class="doc-title">Preventivo<span>.</span></h1>
      <div class="facts">
        <div><span class="lbl">Numero</span><b><?= h($p['numero']) ?></b></div>
        <div><span class="lbl">Data</span><b><?= d($p['data']) ?></b></div>
        <div><span class="lbl">Valido fino al</span><b><?= d($scade) ?></b></div>
      </div>
    </div>
    <div class="to">
      <div class="lbl">Per</div>
      <b><?= h($c['ragione_sociale']) ?></b><br>
      <?php if ($ref): ?>c.a. <?= h($ref['nome']) ?><?= $ref['ruolo'] ? ', ' . h($ref['ruolo']) : '' ?><br><?php endif; ?>
      <?php if ($c['indirizzo']): ?><?= h($c['indirizzo']) ?>, <?= h(trim($c['cap'] . ' ' . $c['citta'] . ($c['provincia'] ? ' (' . $c['provincia'] . ')' : ''))) ?><br><?php endif; ?>
      <?php if ($c['piva']): ?>P.IVA <?= h($c['piva']) ?><?php endif; ?>
    </div>
  </div>
  <div class="lbl">Oggetto</div>
  <p style="font:700 17px 'Bricolage Grotesque',sans-serif;margin:0 0 5mm"><?= h($p['oggetto']) ?></p>
  <?php if ($p['introduzione']): ?><p class="intro"><?= h($p['introduzione']) ?></p><?php endif; ?>
  <table>
    <thead><tr><th>Descrizione</th><th class="n">Q.tà</th><th class="n">Prezzo</th><th class="n">Importo</th></tr></thead>
    <tbody>
    <?php foreach ($righe as $r): ?>
      <tr><td class="desc"><?= h($r['descrizione']) ?></td><td class="n"><?= num_it($r['quantita']) ?></td><td class="n"><?= money($r['prezzo']) ?></td><td class="n"><?= money($r['quantita'] * $r['prezzo']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="sum">
    <?php if ($tot['sconto'] > 0): ?><div><span>Subtotale</span><span><?= money($tot['subtotale']) ?></span></div><div><span>Sconto</span><span>− <?= money($tot['sconto']) ?></span></div><?php endif; ?>
    <div><span>Imponibile</span><span><?= money($tot['imponibile']) ?></span></div>
    <div><span>IVA <?= num_it($p['iva_percentuale']) ?>%</span><span><?= money($tot['iva']) ?></span></div>
    <div class="grand"><span>Totale</span><span><?= money($tot['totale']) ?></span></div>
  </div>
  <div class="cond">
    <div><?php if ($p['condizioni']): ?><div class="lbl">Condizioni</div><p><?= h($p['condizioni']) ?></p><?php endif; ?></div>
    <div><?php if ($az['iban']): ?><div class="lbl">Coordinate bancarie</div><p><?= h($az['iban']) ?></p><?php endif; ?></div>
  </div>
  <div class="sign"><div>Luogo e data</div><div>Timbro e firma per accettazione</div></div>
  <div class="foot"><span><?= h(setting('nota_fiscale')) ?></span><span><?= h($az['nome']) ?> · Preventivo <?= h($p['numero']) ?></span></div>
</div>
</body></html>
