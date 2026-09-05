<?php
use App\Core\Auth;
use App\Core\Csrf;

$roles = ['owner' => 'Propietari', 'admin' => 'Administrador', 'staff' => 'Personal'];
?>

<div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start;">

    <div class="panel">
        <div class="panel__head"><div><h2>Usuaris del panell</h2><p>Qui pot accedir a la gestió de la plataforma.</p></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nom</th><th>Rol</th><th>Últim accés</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?= e($user['name']) ?>
                                <?= (int) $user['id'] === Auth::id() ? ' <span class="badge badge--muted">vós</span>' : '' ?>
                                <br><span style="color:var(--pdsh-muted);font-size:.83rem;"><?= e($user['email']) ?></span>
                            </td>
                            <td><span class="badge"><?= e($roles[$user['role']] ?? $user['role']) ?></span></td>
                            <td style="font-size:.85rem;"><?= dt($user['last_login_at']) ?></td>
                            <td style="text-align:right;">
                                <?php if ($user['role'] !== 'owner' && (int) $user['id'] !== Auth::id() && Auth::is('owner', 'admin')): ?>
                                    <form method="post" action="<?= e(url('/admin/usuaris/' . $user['id'] . '/eliminar')) ?>"
                                          data-confirm="Eliminar l'usuari <?= e($user['email']) ?>?">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:grid;gap:22px;">
        <?php if (Auth::is('owner', 'admin')): ?>
            <div class="panel">
                <div class="panel__head"><div><h2>Afegir un usuari</h2></div></div>
                <div class="panel__body">
                    <form method="post" action="<?= e(url('/admin/usuaris')) ?>">
                        <?= Csrf::field() ?>
                        <div class="field">
                            <label for="new_name">Nom</label>
                            <input class="input" type="text" id="new_name" name="name" value="<?= old('name') ?>" required>
                        </div>
                        <div class="field">
                            <label for="new_email">Correu electrònic</label>
                            <input class="input" type="email" id="new_email" name="email" value="<?= old('email') ?>" required>
                        </div>
                        <div class="field">
                            <label for="new_password">Contrasenya</label>
                            <input class="input" type="password" id="new_password" name="password" autocomplete="new-password" required>
                            <span class="field__hint">Com a mínim 10 caràcters.</span>
                        </div>
                        <div class="field">
                            <label for="new_role">Rol</label>
                            <select class="select" id="new_role" name="role">
                                <option value="staff">Personal — control d'accés i consulta</option>
                                <option value="admin">Administrador — accés complet</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--primary btn--block">Crear l'usuari</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel__head"><div><h2>Canviar la meva contrasenya</h2></div></div>
            <div class="panel__body">
                <form method="post" action="<?= e(url('/admin/usuaris/contrasenya')) ?>">
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label for="current_password">Contrasenya actual</label>
                        <input class="input" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="new_password_1">Contrasenya nova</label>
                        <input class="input" type="password" id="new_password_1" name="new_password" autocomplete="new-password" required>
                    </div>
                    <div class="field">
                        <label for="new_password_2">Repeteix la contrasenya nova</label>
                        <input class="input" type="password" id="new_password_2" name="new_password_confirm" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn--light btn--block">Actualitzar la contrasenya</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head"><div><h2>Registre d'activitat</h2><p>Darreres accions fetes des del panell.</p></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Data</th><th>Usuari</th><th>Acció</th><th>Element</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($audit as $entry): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= dt((string) $entry['created_at']) ?></td>
                        <td><?= e($entry['actor']) ?></td>
                        <td class="mono" style="font-size:.83rem;"><?= e($entry['action']) ?></td>
                        <td><?= e($entry['target'] ?: '—') ?></td>
                        <td style="color:var(--pdsh-muted);font-size:.82rem;"><?= e($entry['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($audit === []): ?>
                    <tr><td colspan="5" class="empty">Encara no hi ha activitat registrada.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
