<?php
use App\Core\Csrf;
use App\Core\View;
use App\Core\Settings;
use App\Services\QrCode;

$token = (string) $order['manage_token'];
$manageUrl = url('/comanda/' . $order['reference']) . '?t=' . $token;
$valid = array_values(array_filter($tickets, static fn ($t) => in_array($t['status'], ['valid', 'used'], true)));
?>

<div data-confetti hidden></div>

<section class="wrap wrap--narrow">
    <div class="success-hero">
        <span class="success-hero__icon" role="img" aria-label="Celebració">🎉</span>
        <h1>Compra realitzada correctament!</h1>
        <p>
            Ja teniu la vostra plaça per al <strong><?= e(Settings::get('event_name')) ?></strong>.
            <?php if (!empty($order['confirmation_sent_at'])): ?>
                Us hem enviat les entrades a <strong><?= e($order['email']) ?></strong>.
            <?php else: ?>
                Descarregueu-vos les entrades aquí sota o envieu-vos-les per correu a
                <strong><?= e($order['email']) ?></strong>.
            <?php endif; ?>
        </p>
        <p style="font-size:.92rem;">
            Referència de la inscripció:
            <strong style="font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.05em;"><?= e($order['reference']) ?></strong>
        </p>
    </div>

    <div class="action-grid">
        <a class="action-tile" href="<?= e(url('/comanda/' . $order['reference'] . '/pdf') . '?t=' . $token) ?>">
            <span class="action-tile__icon" aria-hidden="true">📄</span>
            <strong>Descarregar en PDF</strong>
            <span>Per imprimir o desar al mòbil</span>
        </a>

        <button type="submit" form="send-tickets" class="action-tile" style="border:2px solid var(--pdsh-line);cursor:pointer;font:inherit;">
            <span class="action-tile__icon" aria-hidden="true">✉️</span>
            <strong>Enviar per correu</strong>
            <span>Torna a enviar el PDF adjunt</span>
        </button>
    </div>

    <?= View::partial('web/_wallet_buttons', [
        'base'         => url('/comanda/' . $order['reference']),
        'token'        => $token,
        'walletApple'  => $walletApple,
        'walletGoogle' => $walletGoogle,
        'ticketCount'  => count($valid),
    ]) ?>

    <form id="send-tickets" method="post" action="<?= e(url('/comanda/' . $order['reference'] . '/enviar')) ?>" hidden>
        <?= Csrf::field() ?>
        <input type="hidden" name="t" value="<?= e($token) ?>">
    </form>

    <?php if (!$walletApple && !$walletGoogle): ?>
        <p style="text-align:center;color:var(--pdsh-muted);font-size:.85rem;margin-top:16px;">
            Els passis per a l'Apple Wallet i el Google Wallet encara no estan activats per a aquest esdeveniment.
        </p>
    <?php endif; ?>

    <section class="section">
        <h2>Les vostres entrades</h2>
        <div class="ticket-list">
            <?php foreach ($valid as $ticket): ?>
                <div class="ticket-row">
                    <div class="ticket-row__main">
                        <p class="ticket-row__name"><?= e($ticket['attendee_name'] ?: trim((string) $order['name'] . ' ' . (string) $order['surname'])) ?></p>
                        <span class="ticket-row__type"><?= e($ticket['type_name']) ?> · <?= money((int) $ticket['price_cents']) ?></span>
                    </div>
                    <span class="ticket-row__code"><?= e($ticket['code']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($valid) === 1): ?>
            <div class="qr-box" style="margin-top:24px;">
                <img src="<?= e(QrCode::dataUri(\App\Core\Url::full('/e/' . $valid[0]['code']), 420)) ?>"
                     alt="Codi QR de l'entrada <?= e($valid[0]['code']) ?>">
                <p style="color:var(--pdsh-muted);font-size:.88rem;margin-top:10px;">
                    Mostreu aquest codi a l'entrada de l'esdeveniment.
                </p>
            </div>
        <?php endif; ?>
    </section>

    <div class="card" style="margin-bottom:40px;">
        <div class="card__body">
            <h3>Què cal saber</h3>
            <ul style="padding-left:20px;margin:0;color:var(--pdsh-muted);">
                <li><strong>Data:</strong> <?= e(Settings::get('event_date_text')) ?></li>
                <?php if (($place = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) !== ''): ?>
                    <li><strong>Lloc:</strong> <?= e($place) ?></li>
                <?php endif; ?>
                <li>Podeu tornar a consultar les entrades en qualsevol moment des de
                    <a href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>.</li>
                <li><a href="<?= e($manageUrl) ?>">Gestionar o anul·lar aquesta inscripció</a></li>
            </ul>
        </div>
    </div>
</section>
