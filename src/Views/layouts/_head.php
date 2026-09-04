<?php
/** Fragment comú: metadades, variables de color i fulls d'estil. */
use App\Core\Settings;
use App\Core\Url;

$pageTitle = ($title ?? '') !== ''
    ? $title . ' · ' . Settings::get('event_name')
    : (string) Settings::get('event_name');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription ?? Settings::get('event_description')) ?>">
<meta name="theme-color" content="<?= e(Settings::get('brand_primary')) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription ?? Settings::get('event_description')) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e(Url::base() . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
<?php if (($poster = (string) Settings::get('event_poster')) !== ''): ?>
    <meta property="og:image" content="<?= e(Url::base() . $poster) ?>">
<?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="' . Settings::get('brand_primary') . '"/><text x="16" y="22" font-family="sans-serif" font-size="16" font-weight="bold" fill="' . Settings::get('brand_accent') . '" text-anchor="middle">P</text></svg>') ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
    :root {
        --pdsh-primary: <?= e(Settings::get('brand_primary')) ?>;
        --pdsh-secondary: <?= e(Settings::get('brand_secondary')) ?>;
        --pdsh-accent: <?= e(Settings::get('brand_accent')) ?>;
        --pdsh-cream: <?= e(Settings::get('brand_cream')) ?>;
        --pdsh-olive: <?= e(Settings::get('brand_olive')) ?>;
        --pdsh-ink: <?= e(Settings::get('brand_ink')) ?>;
    }
</style>
