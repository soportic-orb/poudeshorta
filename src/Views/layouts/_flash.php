<?php
/** Missatges d'estat de l'última acció. */
use App\Core\Flash;

$messages = Flash::pull();
if ($messages === []) {
    return;
}
$icons = ['success' => '✅', 'error' => '⛔', 'warning' => '⚠️', 'info' => 'ℹ️'];
?>
<div class="flash-stack">
    <?php foreach ($messages as $message): ?>
        <div class="alert alert--<?= e($message['type']) ?>" role="alert">
            <span aria-hidden="true"><?= $icons[$message['type']] ?? 'ℹ️' ?></span>
            <span><?= e($message['message']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
