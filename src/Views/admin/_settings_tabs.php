<?php
use App\Core\Request;

$tabs = [
    '/admin/configuracio'             => 'Esdeveniment',
    '/admin/configuracio/aparenca'    => 'Aparença',
    '/admin/configuracio/pagaments'   => 'Pagaments',
    '/admin/configuracio/correu'      => 'Correu',
    '/admin/configuracio/anullacions' => 'Anul·lacions',
    '/admin/configuracio/wallet'      => 'Wallet',
];
$current = Request::path();
?>
<nav class="tabs" aria-label="Seccions de configuració">
    <?php foreach ($tabs as $path => $label): ?>
        <a class="<?= $current === $path ? 'is-active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
