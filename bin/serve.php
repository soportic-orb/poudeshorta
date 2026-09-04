<?php
declare(strict_types=1);

// Servidor de desenvolupament local. NO s'utilitza en producció.
//
//   php -S 127.0.0.1:8000 -t public bin/serve.php
//
// Imita el comportament d'Nginx a CloudPanel: els fitxers existents es
// serveixen tal qual i la resta de peticions van al controlador frontal.

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = dirname(__DIR__) . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require dirname(__DIR__) . '/public/index.php';
