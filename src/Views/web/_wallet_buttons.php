<?php
/**
 * Botons oficials «Add to Apple Wallet» i «Add to Google Wallet».
 *
 * Espera: $base (URL de la comanda), $token, $walletApple, $walletGoogle i
 * $ticketCount (quantes entrades s'hi afegiran).
 *
 * Els dos botons afegeixen totes les entrades de la comanda de cop.
 */

if (empty($walletApple) && empty($walletGoogle)) {
    return;
}

$ticketCount = max(1, (int) ($ticketCount ?? 1));
$peu = $ticketCount === 1
    ? 'S\'hi afegirà la vostra entrada.'
    : 'S\'hi afegiran les ' . $ticketCount . ' entrades de cop.';
?>
<div class="wallet-buttons">
    <?php if ($walletApple): ?>
        <a class="wallet-btn" href="<?= e($base . '/wallet/apple?t=' . $token) ?>">
            <img class="wallet-btn__icon" src="<?= e(asset('img/wallet/apple-wallet.svg')) ?>" alt="" width="48" height="48">
            <span class="wallet-btn__text">Add to Apple Wallet</span>
        </a>
    <?php endif; ?>

    <?php if ($walletGoogle): ?>
        <a class="wallet-btn" href="<?= e($base . '/wallet/google?t=' . $token) ?>">
            <img class="wallet-btn__icon" src="<?= e(asset('img/wallet/google-wallet.svg')) ?>" alt="" width="48" height="48">
            <span class="wallet-btn__text">Add to Google Wallet</span>
        </a>
    <?php endif; ?>
</div>
<p class="wallet-buttons__note"><?= e($peu) ?></p>
