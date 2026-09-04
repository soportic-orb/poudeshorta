<?php
use App\Core\Csrf;

$heading = 'Instal·lació de la plataforma';
$subheading = 'Inscripcions · Pou de s\'Horta';
$errors = $errors ?? [];
?>

<h2 style="font-size:1.05rem;margin-top:0;">1 · Requisits del servidor</h2>
<table class="summary-table" style="margin-bottom:24px;">
    <?php foreach ($requirements as $requirement): ?>
        <tr>
            <th style="width:auto;color:var(--pdsh-ink);font-weight:600;">
                <?= $requirement['ok'] ? '✅' : '⛔' ?> <?= e($requirement['label']) ?>
            </th>
            <td style="text-align:right;color:var(--pdsh-muted);font-size:.85rem;"><?= e($requirement['hint']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (!$ready): ?>
    <div class="alert alert--error">
        <span aria-hidden="true">⛔</span>
        <span>Cal resoldre els punts marcats abans de continuar. Reviseu els permisos dels directoris
              (<code>chmod 775</code>) i les extensions de PHP al panell de CloudPanel.</span>
    </div>
<?php else: ?>

<form method="post" action="<?= e(url('/install')) ?>" data-guard>
    <?= Csrf::field() ?>

    <h2 style="font-size:1.05rem;">2 · Base de dades MySQL</h2>
    <p style="color:var(--pdsh-muted);font-size:.9rem;">
        Creeu la base de dades i l'usuari des de CloudPanel (Databases → Add Database) i escriviu-ne les dades aquí.
    </p>

    <div class="field-row">
        <div class="field">
            <label for="db_host">Servidor</label>
            <input class="input" type="text" id="db_host" name="db_host" value="<?= old('db_host', '127.0.0.1') ?>" required>
        </div>
        <div class="field">
            <label for="db_port">Port</label>
            <input class="input" type="number" id="db_port" name="db_port" value="<?= old('db_port', '3306') ?>">
        </div>
    </div>

    <div class="field">
        <label for="db_name">Nom de la base de dades</label>
        <input class="input <?= isset($errors['db_host']) ? 'has-error' : '' ?>" type="text" id="db_name" name="db_name" value="<?= old('db_name') ?>" required>
        <?php if (isset($errors['db_host'])): ?><span class="field__error"><?= e($errors['db_host']) ?></span><?php endif; ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="db_user">Usuari</label>
            <input class="input" type="text" id="db_user" name="db_user" value="<?= old('db_user') ?>" required>
        </div>
        <div class="field">
            <label for="db_pass">Contrasenya</label>
            <input class="input" type="password" id="db_pass" name="db_pass" autocomplete="new-password">
        </div>
    </div>

    <h2 style="font-size:1.05rem;margin-top:26px;">3 · Administrador del panell</h2>

    <div class="field">
        <label for="admin_name">Nom</label>
        <input class="input" type="text" id="admin_name" name="admin_name" value="<?= old('admin_name') ?>" required>
    </div>

    <div class="field">
        <label for="admin_email">Correu electrònic</label>
        <input class="input <?= isset($errors['admin_email']) ? 'has-error' : '' ?>" type="email" id="admin_email" name="admin_email" value="<?= old('admin_email') ?>" required>
        <?php if (isset($errors['admin_email'])): ?><span class="field__error"><?= e($errors['admin_email']) ?></span><?php endif; ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="admin_password">Contrasenya</label>
            <input class="input <?= isset($errors['admin_password']) ? 'has-error' : '' ?>" type="password" id="admin_password" name="admin_password" autocomplete="new-password" required>
            <span class="field__hint">Com a mínim 10 caràcters.</span>
            <?php if (isset($errors['admin_password'])): ?><span class="field__error"><?= e($errors['admin_password']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label for="admin_password_confirm">Repetiu la contrasenya</label>
            <input class="input <?= isset($errors['admin_password_confirm']) ? 'has-error' : '' ?>" type="password" id="admin_password_confirm" name="admin_password_confirm" autocomplete="new-password" required>
            <?php if (isset($errors['admin_password_confirm'])): ?><span class="field__error"><?= e($errors['admin_password_confirm']) ?></span><?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label for="base_url">Adreça pública del lloc</label>
        <input class="input" type="url" id="base_url" name="base_url" value="<?= old('base_url', $baseUrl) ?>">
        <span class="field__hint">S'utilitza als correus, als codis QR i al webhook de Stripe.</span>
    </div>

    <button type="submit" class="btn btn--primary btn--block btn--lg" data-loading="Instal·lant…">
        Instal·lar la plataforma
    </button>
</form>

<?php endif; ?>
