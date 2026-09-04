<?php
use App\Core\Settings;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="ca">
<head>
<?= View::partial('layouts/_head', get_defined_vars()) ?>
</head>
<body>

<header class="site-header">
    <div class="wrap site-header__inner">
        <a class="site-header__brand" href="<?= e(url('/')) ?>">
            <?php if (($logo = (string) Settings::get('event_logo')) !== ''): ?>
                <img class="site-header__logo" src="<?= e(url($logo)) ?>" alt="">
            <?php else: ?>
                <span class="site-header__mark" aria-hidden="true">P</span>
            <?php endif; ?>
            <span class="site-header__title">
                <?= e(Settings::get('event_name')) ?>
                <small><?= e(Settings::get('event_date_text')) ?></small>
            </span>
        </a>

        <nav class="site-nav" aria-label="Navegació principal">
            <a href="<?= e(url('/')) ?>#informacio">Informació</a>
            <a href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>
            <?php if (\App\Services\TicketService::salesOpen()): ?>
                <a class="is-cta" href="<?= e(url('/')) ?>#inscripcions">Inscriure's</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main>
    <?php if (!empty($showFlashInWrap ?? true)): ?>
        <?php $flash = View::partial('layouts/_flash'); ?>
        <?php if (trim($flash) !== ''): ?>
            <div class="wrap" style="padding-top:20px;"><?= $flash ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="site-footer__grid">
            <div>
                <h4><?= e(Settings::get('event_name')) ?></h4>
                <p><?= e(Settings::get('event_date_text')) ?><br>
                   <?= e(trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) ?></p>
            </div>
            <div>
                <h4>Contacte</h4>
                <p>
                    <?php if (($email = (string) Settings::get('event_contact_email')) !== ''): ?>
                        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><br>
                    <?php endif; ?>
                    <?php if (($phone = (string) Settings::get('event_contact_phone')) !== ''): ?>
                        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <h4>Entrades</h4>
                <p>
                    <a href="<?= e(url('/les-meves-entrades')) ?>">Consultar o anul·lar la inscripció</a><br>
                    <a href="<?= e(url('/informacio')) ?>">Condicions i privacitat</a>
                </p>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>© <?= date('Y') ?> · <?= e(Settings::get('event_organizer')) ?></span>
            <span>Pagaments segurs amb Stripe · poudeshorta.cat</span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($useConfetti)): ?>
    <script src="<?= e(asset('js/confetti.js')) ?>" defer></script>
<?php endif; ?>
<?php if (($ga = trim((string) Settings::get('google_analytics'))) !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= e($ga) ?>');
    </script>
<?php endif; ?>
</body>
</html>
