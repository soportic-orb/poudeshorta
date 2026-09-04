<?php
use App\Core\Csrf;
use App\Core\Settings;

$heading = 'Panell de Gestió';
$subheading = (string) Settings::get('event_name');
?>

<form method="post" action="<?= e(url('/admin/login')) ?>" data-guard>
    <?= Csrf::field() ?>

    <div class="field">
        <label for="email">Correu electrònic</label>
        <input class="input" type="email" id="email" name="email" autocomplete="username" required autofocus>
    </div>

    <div class="field">
        <label for="password">Contrasenya</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn btn--primary btn--block btn--lg" data-loading="Entrant…">Entrar</button>
</form>
