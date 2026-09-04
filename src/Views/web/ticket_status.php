<?php
use App\Core\Csrf;
use App\Core\Settings;

/**
 * Pàgina a què porta el codi QR de l'entrada.
 * L'estat es veu abans de llegir res: la franja de color n'és el missatge.
 */
$state = $state ?? 'unknown';

$config = match ($state) {
    'valid'     => ['icon' => '✅', 'title' => 'Entrada vàlida',       'tone' => 'ok'],
    'used'      => ['icon' => '⚠️', 'title' => 'Ja validada',          'tone' => 'warning'],
    'cancelled' => ['icon' => '⛔', 'title' => 'Entrada anul·lada',    'tone' => 'error'],
    'unpaid'    => ['icon' => '⛔', 'title' => 'Inscripció no pagada', 'tone' => 'error'],
    default     => ['icon' => '❓', 'title' => 'Entrada desconeguda',  'tone' => 'error'],
};

$attendee = $ticket === null ? '' : trim((string) ($ticket['attendee_name']
    ?: $ticket['buyer_name'] . ' ' . $ticket['buyer_surname']));
?>

<div class="state-banner state-banner--<?= $config['tone'] ?>">
    <span class="state-banner__icon" aria-hidden="true"><?= $config['icon'] ?></span>
    <p class="state-banner__title"><?= e($config['title']) ?></p>

    <?php if ($ticket === null): ?>
        <p class="state-banner__note">Aquest codi no correspon a cap entrada d'aquest esdeveniment.</p>
    <?php else: ?>
        <?php if ($attendee !== ''): ?>
            <p class="state-banner__name"><?= e($attendee) ?></p>
        <?php endif; ?>

        <?php if ($state === 'used' && !empty($ticket['checked_in_at'])): ?>
            <p class="state-banner__note">
                Es va validar el <?= dt((string) $ticket['checked_in_at'], 'd/m/Y \a \l\e\s H:i') ?>.
            </p>
        <?php elseif ($state === 'valid'): ?>
            <p class="state-banner__note">Endavant, que vagi molt bé!</p>
        <?php elseif ($state === 'unpaid'): ?>
            <p class="state-banner__note">Aquesta inscripció no consta com a pagada.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<section class="section">
    <div class="wrap wrap--narrow">
        <?php if ($ticket !== null): ?>
            <div class="card">
                <div class="card__body">
                    <table class="summary-table">
                        <tr><th>Tipus</th><td><?= e($ticket['type_name']) ?></td></tr>
                        <tr>
                            <th>Codi</th>
                            <td style="font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.08em;font-weight:700;">
                                <?= e($ticket['code']) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Esdeveniment</th>
                            <td>
                                <?= e(Settings::get('event_name')) ?><br>
                                <span style="color:var(--pdsh-muted);"><?= e(Settings::get('event_date_text')) ?></span>
                            </td>
                        </tr>
                        <?php if (($place = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) !== ''): ?>
                            <tr><th>Lloc</th><td><?= e($place) ?></td></tr>
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
                    <button type="submit" class="btn btn--primary btn--block btn--lg">Validar l'entrada ara</button>
                </form>
                <p style="text-align:center;margin-top:10px;font-size:.88rem;">
                    <a href="<?= e(url('/admin/control-acces')) ?>">Anar al control d'accés →</a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="card__body" style="text-align:center;">
                    <p style="margin:0;color:var(--pdsh-muted);">
                        Comproveu que heu escanejat bé el codi. Si el problema continua, poseu-vos en
                        contacte amb l'organització.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <p style="text-align:center;margin-top:26px;">
            <a href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>
            &nbsp;·&nbsp;
            <a href="<?= e(url('/')) ?>">Inici</a>
        </p>
    </div>
</section>
