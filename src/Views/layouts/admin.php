<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Settings;
use App\Core\View;
use App\Services\MailQueue;
use App\Services\Updater;

$user = Auth::user() ?? ['name' => '', 'email' => '', 'role' => ''];
$path = Request::path();

$isActive = static function (string $prefix) use ($path): string {
    if ($prefix === '/admin') {
        return $path === '/admin' ? ' is-active' : '';
    }
    return str_starts_with($path, $prefix) ? ' is-active' : '';
};

$updateAvailable = ($latest = trim((string) Settings::get('ota_latest_version'))) !== ''
    && $latest !== Updater::currentVersion()
    && $latest !== (Updater::currentCommit() ?? '');

try {
    $queued = MailQueue::pendingCount();
} catch (Throwable) {
    $queued = 0;
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
<?= View::partial('layouts/_head', get_defined_vars()) ?>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">

<aside class="admin-side">
    <a class="admin-side__brand" href="<?= e(url('/admin')) ?>">
        <span class="admin-side__mark" aria-hidden="true">PH</span>
        <span>
            <strong>Panell de Gestió</strong>
            <small><?= e(\App\Core\Str::limit((string) Settings::get('event_name'), 28)) ?></small>
        </span>
    </a>

    <nav class="admin-nav" aria-label="Menú del panell">
        <a class="<?= trim($isActive('/admin')) ?>" href="<?= e(url('/admin')) ?>"><i class="ico">▦</i> Resum</a>

        <span class="admin-nav__group">Inscripcions</span>
        <a class="<?= trim($isActive('/admin/inscripcions')) ?>" href="<?= e(url('/admin/inscripcions')) ?>"><i class="ico">☰</i> Llistat</a>
        <a class="<?= trim($isActive('/admin/tipus-inscripcio')) ?>" href="<?= e(url('/admin/tipus-inscripcio')) ?>"><i class="ico">🎟</i> Tipus d'inscripció</a>
        <a class="<?= trim($isActive('/admin/control-acces')) ?>" href="<?= e(url('/admin/control-acces')) ?>"><i class="ico">✓</i> Control d'accés</a>

        <span class="admin-nav__group">Comunicació</span>
        <a class="<?= trim($isActive('/admin/comunicacions')) ?>" href="<?= e(url('/admin/comunicacions')) ?>">
            <i class="ico">✉</i> Comunicats
            <?php if ($queued > 0): ?><span class="pill"><?= (int) $queued ?></span><?php endif; ?>
        </a>

        <span class="admin-nav__group">Configuració</span>
        <a class="<?= $path === '/admin/configuracio' ? 'is-active' : '' ?>" href="<?= e(url('/admin/configuracio')) ?>"><i class="ico">◉</i> Esdeveniment</a>
        <a class="<?= trim($isActive('/admin/configuracio/aparenca')) ?>" href="<?= e(url('/admin/configuracio/aparenca')) ?>"><i class="ico">🎨</i> Aparença</a>
        <a class="<?= trim($isActive('/admin/configuracio/pagaments')) ?>" href="<?= e(url('/admin/configuracio/pagaments')) ?>"><i class="ico">💳</i> Pagaments</a>
        <a class="<?= trim($isActive('/admin/configuracio/correu')) ?>" href="<?= e(url('/admin/configuracio/correu')) ?>"><i class="ico">📮</i> Correu (SMTP)</a>
        <a class="<?= trim($isActive('/admin/configuracio/anullacions')) ?>" href="<?= e(url('/admin/configuracio/anullacions')) ?>"><i class="ico">↩</i> Anul·lacions</a>
        <a class="<?= trim($isActive('/admin/configuracio/wallet')) ?>" href="<?= e(url('/admin/configuracio/wallet')) ?>"><i class="ico">📱</i> Wallet</a>

        <span class="admin-nav__group">Sistema</span>
        <a class="<?= trim($isActive('/admin/usuaris')) ?>" href="<?= e(url('/admin/usuaris')) ?>"><i class="ico">👤</i> Usuaris</a>
        <a class="<?= trim($isActive('/admin/actualitzacions')) ?>" href="<?= e(url('/admin/actualitzacions')) ?>">
            <i class="ico">⟳</i> Actualitzacions
            <?php if ($updateAvailable): ?><span class="pill">1</span><?php endif; ?>
        </a>
        <a class="<?= trim($isActive('/admin/sistema')) ?>" href="<?= e(url('/admin/sistema')) ?>"><i class="ico">⚙</i> Estat del sistema</a>
    </nav>

    <div class="admin-side__foot">
        Versió <?= e(Updater::currentVersion()) ?><?= Updater::currentCommit() ? ' · ' . e(Updater::currentCommit()) : '' ?><br>
        <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Veure el web públic ↗</a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-top">
        <h1><?= e($title ?? 'Panell de Gestió') ?></h1>
        <div class="admin-top__actions">
            <?= $topActions ?? '' ?>
            <span class="admin-top__user"><?= e($user['name']) ?></span>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--light btn--sm">Sortir</button>
            </form>
        </div>
    </header>

    <div class="admin-content">
        <?php if (Settings::bool('maintenance_mode')): ?>
            <div class="alert alert--warning">
                <span aria-hidden="true">⚠️</span>
                <span><strong>Mode manteniment actiu.</strong> El web públic no és accessible per als visitants.
                    Podeu desactivar-lo a <a href="<?= e(url('/admin/configuracio')) ?>">Configuració → Esdeveniment</a>.</span>
            </div>
        <?php endif; ?>

        <?= View::partial('layouts/_flash') ?>
        <?= $content ?? '' ?>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (str_starts_with($path, '/admin/control-acces')): ?>
    <script src="<?= e(asset('js/checkin.js')) ?>" defer></script>
    <script src="<?= e(asset('js/scanner.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
