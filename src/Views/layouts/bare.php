<?php
use App\Core\Settings;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="ca">
<head>
<?= View::partial('layouts/_head', get_defined_vars()) ?>
<style>
    .bare {
        min-height: 100vh; display: grid; place-items: center; padding: 32px 18px;
        background:
            radial-gradient(110% 120% at 12% 0%, rgba(242,168,29,.25) 0%, rgba(242,168,29,0) 55%),
            linear-gradient(165deg, var(--pdsh-primary) 0%, #5E0A18 100%);
    }
    .bare__card {
        width: 100%; max-width: 560px; background: #fff;
        border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.28); overflow: hidden;
    }
    .bare__head { background: var(--pdsh-cream); padding: 26px 30px; border-bottom: 4px solid var(--pdsh-accent); }
    .bare__head h1 { margin: 0; font-size: 1.5rem; color: var(--pdsh-primary); }
    .bare__head p { margin: 6px 0 0; color: var(--pdsh-muted); font-size: .92rem; }
    .bare__body { padding: 28px 30px 32px; }
    .bare__foot { text-align: center; margin-top: 20px; font-size: .84rem; color: rgba(251,244,230,.75); }
    .bare__foot a { color: var(--pdsh-accent); }
    .bare--wide .bare__card { max-width: 840px; }
</style>
</head>
<body>
<div class="bare <?= !empty($wide) ? 'bare--wide' : '' ?>">
    <div>
        <div class="bare__card">
            <div class="bare__head">
                <h1><?= e($heading ?? $title ?? Settings::get('event_name')) ?></h1>
                <?php if (!empty($subheading)): ?><p><?= e($subheading) ?></p><?php endif; ?>
            </div>
            <div class="bare__body">
                <?= View::partial('layouts/_flash') ?>
                <?= $content ?? '' ?>
            </div>
        </div>
        <p class="bare__foot"><a href="<?= e(url('/')) ?>">← Tornar al web de l'esdeveniment</a></p>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
