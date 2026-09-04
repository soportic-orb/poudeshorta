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
<link rel="preload" as="font" type="font/woff2" href="<?= e(url('/assets/fonts/caveat-brush-latin.woff2')) ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= e(url('/assets/fonts/nunito-400-latin.woff2')) ?>" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
    :root {
        --pdsh-primary: <?= e(Settings::get('brand_primary')) ?>;
        --pdsh-secondary: <?= e(Settings::get('brand_secondary')) ?>;
        --pdsh-accent: <?= e(Settings::get('brand_accent')) ?>;
        --pdsh-cream: <?= e(Settings::get('brand_cream')) ?>;
        --pdsh-olive: <?= e(Settings::get('brand_olive')) ?>;
        --pdsh-ink: <?= e(Settings::get('brand_ink')) ?>;
<?php
// Banderoles dibuixades amb els colors configurats, com les del cartell.
$bunting = sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" width="72" height="20" viewBox="0 0 72 20">'
    . '<path d="M0 0h72v2H0z" fill="%1$s" opacity=".45"/>'
    . '<path d="M6 1h22l-11 17z" fill="%2$s"/>'
    . '<path d="M42 1h22l-11 17z" fill="%3$s"/>'
    . '</svg>',
    Settings::get('brand_ink'),
    Settings::get('brand_accent'),
    Settings::get('brand_cream')
);
?>
        --pdsh-bunting: url("data:image/svg+xml,<?= rawurlencode($bunting) ?>");
    }
</style>
