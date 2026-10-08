<?php
// Copia questo file come config.php (lo fa da solo l'installazione guidata in /install/)
return [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'timezone' => 'Europe/Rome',
    'debug' => false,
    // reindirizza sempre su https (lascia true una volta attivato il certificato SSL)
    'force_https' => true,
    // in caso di emergenza (telefono perso): true disattiva la verifica in due passaggi
    'disattiva_2fa' => false,
    // promemoria giornaliero via email (cron.php): true per attivarlo
    'mail_enabled' => false,
];
