<?php
// Scarica un allegato (solo dopo il login)
require __DIR__ . '/inc/bootstrap.php';
require_login();
$a = one('SELECT * FROM allegati WHERE id=?', [(int)get('id')]);
$path = $a ? upload_dir() . '/' . basename($a['file']) : '';
if (!$a || !is_file($path)) { http_response_code(404); exit('File non trovato'); }
$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
$inline = preg_match('#^(image/(png|jpeg|gif|webp)|application/pdf|text/plain)$#', $mime);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
if ($mime !== 'application/pdf') header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($a['nome']));
readfile($path);
