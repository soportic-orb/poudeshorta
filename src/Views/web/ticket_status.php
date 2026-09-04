<?php
use App\Core\Csrf;
use App\Core\Settings;

$config = match ($state) {
    'valid'     => ['icon' => '✅', 'title' => 'Entrada vàlida',        'class' => 'alert--success'],
    'used'      => ['icon' => '⚠️', 'title' => 'Entrada ja validada',   'class' => 'alert--warning'],
    'cancelled' => ['icon' => '⛔', 'title' => 'Entrada anul·lada',     'class' => 'alert--error'],
    'unpaid'    => ['icon' => '⛔', 'title' => 'Inscripció no pagada',  'class' => 'alert--error'],
    default     => ['icon' => '❓', 'title' => 'Entrada desconeguda',   'class' => 'alert--error'],
};
?>

<section class="section">
    <div class="wrap wrap--narrow">
        <div class="success-hero" style="padding-bottom:16px;">
            <span class="success-hero__icon" aria-hidden="true"><?= $config['icon'] ?></span>
            <h1><?= e($config['title']) ?></h1>
        </div>

        <?php if ($ticket === null): ?>
            <div class="alert alert--error">
                <span aria-hidden="true">⛔</span>
                <span>Aquest codi no correspon a cap entrada d'aquest esdeveniment.</span>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card__body">
                    <table class="summary-table">
                        <tr><th>Assistent</th><td><?= e($ticket['attendee_name'] ?: trim((string) $ticket['buyer_name'] . ' ' . (string) $ticket['buyer_surname'])) ?></td></tr>
                        <tr><th>Tipus</th><td><?= e($ticket['type_name']) ?></td></tr>
                        <tr><th>Codi</th><td style="font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.05em;"><?= e($ticket['code']) ?></td></tr>
                        <tr><th>Esdeveniment</th><td><?= e(Settings::get('event_name')) ?><br><span style="color:var(--pdsh-muted);"><?= e(Settings::get('event_date_text')) ?></span></td></tr>
                        <?php if ($state === 'used' && $ticket['checked_in_at']): ?>
                            <tr><th>Validada</th><td><?= dt((string) $ticket['checked_in_at']) ?></td></tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($state === 'valid'): ?>
                        <p style="margin:18px 0 0;color:var(--pdsh-muted);font-size:.92rem;">
                            Mostreu aquesta pantalla o el PDF de l'entrada al personal de l'accés.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isStaff && $state === 'valid' && Settings::bool('checkin_enabled', true)): ?>
                <form method="post" action="<?= e(url('/admin/control-acces/validar')) ?>" style="margin-top:18px;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="code" value="<?= e($ticket['code']) ?>">
                    <button type="submit" class="btn btn--primary btn--block">Validar l'entrada ara</button>
                </form>
                <p style="text-align:center;margin-top:10px;font-size:.86rem;">
                    <a href="<?= e(url('/admin/control-acces')) ?>">Anar al control d'accés →</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <p style="text-align:center;margin-top:26px;">
            <a href="<?= e(url('/')) ?>">Tornar a l'inici</a>
        </p>
    </div>
</section>
