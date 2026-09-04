<?php
use App\Core\Csrf;
use App\Core\View;
use App\Core\Settings;
use App\Services\TicketService;

$token = (string) $order['manage_token'];
$base = url('/comanda/' . $order['reference']);
$valid = array_values(array_filter($tickets, static fn ($t) => $t['status'] === 'valid'));
$paid = in_array((string) $order['status'], ['paid', 'partially_refunded'], true);
?>

<div class="page-head">
    <div class="wrap wrap--narrow">
        <a class="page-head__back" href="<?= e(url('/les-meves-entrades')) ?>">← Les meves entrades</a>
        <h1>Inscripció <?= e($order['reference']) ?></h1>
        <p>
            <?= dt((string) $order['created_at'], 'd/m/Y') ?> ·
            <?= e($order['email']) ?> ·
            <?= e(TicketService::statusLabel((string) $order['status'])) ?>
        </p>
    </div>
</div>

<section class="section">
    <div class="wrap wrap--narrow">

        <?php if ($paid): ?>
            <div class="action-grid" style="margin-bottom:32px;">
                <a class="action-tile" href="<?= e($base . '/pdf?t=' . $token) ?>">
                    <span class="action-tile__icon" aria-hidden="true">📄</span>
                    <strong>Descarregar en PDF</strong>
                    <span>Totes les entrades vàlides</span>
                </a>

                <button type="submit" form="resend" class="action-tile" style="border:2px solid var(--pdsh-line);cursor:pointer;font:inherit;">
                    <span class="action-tile__icon" aria-hidden="true">✉️</span>
                    <strong>Enviar per correu</strong>
                    <span>A <?= e($order['email']) ?></span>
                </button>
            </div>

            <?= View::partial('web/_wallet_buttons', [
                'base'         => $base,
                'token'        => $token,
                'walletApple'  => $walletApple,
                'walletGoogle' => $walletGoogle,
                'ticketCount'  => count($valid),
            ]) ?>

            <form id="resend" method="post" action="<?= e($base . '/enviar') ?>" hidden>
                <?= Csrf::field() ?>
                <input type="hidden" name="t" value="<?= e($token) ?>">
            </form>
        <?php endif; ?>

        <div class="card" style="margin-bottom:22px;">
            <div class="card__body">
                <h2 style="font-size:1.15rem;">Entrades</h2>
                <div class="ticket-list">
                    <?php foreach ($tickets as $ticket):
                        $class = match ($ticket['status']) {
                            'cancelled', 'refunded' => 'is-cancelled',
                            'used' => 'is-used',
                            default => '',
                        };
                    ?>
                        <div class="ticket-row <?= $class ?>">
                            <?php if ($class === 'is-cancelled'): ?>
                                <span class="ticket-row__ribbon"><?= e(TicketService::statusLabel((string) $ticket['status'])) ?></span>
                            <?php endif; ?>
                            <div class="ticket-row__main">
                                <p class="ticket-row__name">
                                    <?= e($ticket['attendee_name'] ?: trim((string) $order['name'] . ' ' . (string) $order['surname'])) ?>
                                </p>
                                <span class="ticket-row__type">
                                    <?= e($ticket['type_name']) ?> · <?= money((int) $ticket['price_cents']) ?>
                                    <?php if ($ticket['status'] !== 'valid' && $class !== 'is-cancelled'): ?>
                                        · <strong><?= e(TicketService::statusLabel((string) $ticket['status'])) ?></strong>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="ticket-row__code"><?= e($ticket['code']) ?></span>

                            <?php if (($walletApple || $walletGoogle) && in_array($ticket['status'], ['valid', 'used'], true)): ?>
                                <span class="ticket-row__wallet">
                                    <?php if ($walletApple): ?>
                                        <a href="<?= e($base . '/wallet/apple?t=' . $token . '&entrada=' . (int) $ticket['id']) ?>"
                                           title="Afegir només aquesta entrada a l'Apple Wallet">
                                            <img src="<?= e(asset('img/wallet/apple-wallet.svg')) ?>" alt="Afegir aquesta entrada a l'Apple Wallet" width="26" height="26">
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($walletGoogle): ?>
                                        <a href="<?= e($base . '/wallet/google?t=' . $token . '&entrada=' . (int) $ticket['id']) ?>"
                                           title="Afegir només aquesta entrada al Google Wallet">
                                            <img src="<?= e(asset('img/wallet/google-wallet.svg')) ?>" alt="Afegir aquesta entrada al Google Wallet" width="26" height="26">
                                        </a>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <table class="summary-table" style="margin-top:20px;">
                    <tr><th>Import pagat</th><td style="text-align:right;"><?= money((int) $order['total_cents']) ?></td></tr>
                    <?php if ((int) $order['refunded_cents'] > 0): ?>
                        <tr><th>Import retornat</th><td style="text-align:right;color:var(--pdsh-danger);">− <?= money((int) $order['refunded_cents']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php if ($paid && $valid !== []): ?>
            <div class="card">
                <div class="card__body">
                    <h2 style="font-size:1.15rem;">Anul·lar la inscripció</h2>

                    <?php if (!$cancellation['allowed']): ?>
                        <div class="alert alert--info" style="margin:0;">
                            <span aria-hidden="true">ℹ️</span>
                            <span><?= e($cancellation['reason']) ?></span>
                        </div>
                        <?php if (($contact = (string) Settings::get('event_contact_email')) !== ''): ?>
                            <p style="margin:14px 0 0;color:var(--pdsh-muted);font-size:.92rem;">
                                Si teniu qualsevol dubte, escriviu-nos a <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color:var(--pdsh-muted);font-size:.94rem;">
                            <?= nl2br(e(Settings::get('cancellation_policy_text'))) ?>
                            <?php if ($deadline !== null): ?>
                                <br><strong>Termini per anul·lar: <?= date('d/m/Y', $deadline) ?>.</strong>
                            <?php endif; ?>
                            <?php $fee = (float) Settings::get('cancellation_fee_percent', '0'); ?>
                            <?php if ($fee > 0): ?>
                                <br>Es retindrà un <?= e(rtrim(rtrim(number_format($fee, 2, ',', '.'), '0'), ',')) ?>% en concepte de despeses de gestió.
                            <?php endif; ?>
                        </p>

                        <form method="post" action="<?= e($base . '/anullar') ?>"
                              data-confirm="Segur que voleu anul·lar les entrades seleccionades? Aquesta acció no es pot desfer.">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="t" value="<?= e($token) ?>">

                            <?php if (Settings::bool('cancellation_allow_partial', true) && count($valid) > 1): ?>
                                <fieldset>
                                    <legend style="font-size:.95rem;">Trieu quines entrades voleu anul·lar</legend>
                                    <?php foreach ($valid as $ticket): ?>
                                        <label class="check" style="margin-bottom:9px;">
                                            <input type="checkbox" name="tickets[]" value="<?= (int) $ticket['id'] ?>">
                                            <span>
                                                <?= e($ticket['attendee_name'] ?: 'Entrada') ?>
                                                — <?= e($ticket['type_name']) ?> (<?= money((int) $ticket['price_cents']) ?>)
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                    <span class="field__hint">Si no en marqueu cap, s'anul·larà la inscripció sencera.</span>
                                </fieldset>
                            <?php endif; ?>

                            <button type="submit" class="btn btn--danger">Anul·lar la inscripció</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
