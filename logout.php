<?php
require __DIR__ . '/inc/bootstrap.php';
// si esce solo con il link della piattaforma (protegge da link esterni che ti scollegano)
if (user() && hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) {
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
}
header('Location: login.php');
