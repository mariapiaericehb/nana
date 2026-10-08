<?php defined('APP_ROOT') || defined('INSTALL') || exit;
// Piccole azioni via JavaScript (trascinamento nelle bacheche, spunta dei task)
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{}'); }
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
$a = $in['azione'] ?? '';

function ok(array $x = []): never { echo json_encode(['ok' => true] + $x); exit; }

switch ($a) {
    case 'sposta_task': {
        $id = (int)($in['id'] ?? 0);
        $stato = pick($in['stato'] ?? '', STATI_TASK, '');
        if (!$id || !$stato) { http_response_code(400); exit('{"ok":false}'); }
        $old = one('SELECT stato FROM task WHERE id=?', [$id]);
        $data = ['stato' => $stato];
        if ($stato === 'fatto' && $old['stato'] !== 'fatto') $data['completato_il'] = date('Y-m-d H:i:s');
        if ($stato !== 'fatto') $data['completato_il'] = null;
        update('task', $id, $data);
        foreach (array_values((array)($in['ordine'] ?? [])) as $i => $tid) q('UPDATE task SET ordine=? WHERE id=?', [$i, (int)$tid]);
        ok();
    }
    case 'spunta_task': {
        $id = (int)($in['id'] ?? 0);
        $fatto = !empty($in['fatto']);
        update('task', $id, ['stato' => $fatto ? 'fatto' : 'da_fare', 'completato_il' => $fatto ? date('Y-m-d H:i:s') : null]);
        ok();
    }
    case 'sposta_trattativa': {
        $id = (int)($in['id'] ?? 0);
        $fase = pick($in['fase'] ?? '', FASI, '');
        $t = one('SELECT * FROM trattative WHERE id=?', [$id]);
        if (!$t || !$fase) { http_response_code(400); exit('{"ok":false}'); }
        if ($t['fase'] !== $fase) {
            update('trattative', $id, ['fase' => $fase, 'probabilita' => FASI_PROB[$fase]]);
            log_act((int)$t['cliente_id'], 'Trattativa "' . $t['titolo'] . '" spostata in: ' . FASI[$fase], url('trattative'));
            if ($fase === 'vinta') q("UPDATE clienti SET stato='cliente' WHERE id=? AND stato='potenziale'", [$t['cliente_id']]);
        }
        foreach (array_values((array)($in['ordine'] ?? [])) as $i => $tid) q('UPDATE trattative SET ordine=? WHERE id=?', [$i, (int)$tid]);
        ok();
    }
}
http_response_code(400);
echo '{"ok":false}';
