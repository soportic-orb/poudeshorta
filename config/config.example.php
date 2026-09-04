<?php
/**
 * Exemple de configuració.
 *
 * Normalment NO cal editar aquest fitxer: l'instal·lador web crea
 * config/config.php automàticament la primera vegada que visiteu el lloc.
 * Copieu-lo manualment només si preferiu configurar-ho a mà.
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'poudeshorta',
        'user'    => 'poudeshorta',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Cadena aleatòria pròpia de la instal·lació.
    'app_key'  => '',

    // Adreça pública del lloc, sense barra final.
    // S'utilitza als correus, als codis QR de les entrades i al webhook de Stripe.
    'base_url' => 'https://poudeshorta.online',

    // Poseu-ho a true només mentre depureu: mostra els errors pel navegador.
    'debug'    => false,
];
