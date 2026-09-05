<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$s = $settings;
?>

<?= View::partial('admin/_settings_tabs') ?>

<?php if (!$smtpReady): ?>
    <div class="alert alert--warning">
        <span aria-hidden="true">⚠️</span>
        <span>Sense servidor SMTP no es poden enviar ni les entrades ni els comunicats.
              A CloudPanel pots crear un compte de correu del domini i fer-lo servir aquí.</span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/configuracio/correu')) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head"><div><h2>Servidor SMTP</h2><p>Dades del servidor de sortida de correu.</p></div></div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="smtp_host">Servidor</label>
                    <input class="input" type="text" id="smtp_host" name="smtp_host" value="<?= e($s['smtp_host']) ?>"
                           placeholder="smtp.elmeuservidor.cat">
                </div>
                <div class="field">
                    <label for="smtp_port">Port</label>
                    <input class="input" type="number" id="smtp_port" name="smtp_port" value="<?= e($s['smtp_port']) ?>">
                    <span class="field__hint">587 amb STARTTLS o 465 amb SSL.</span>
                </div>
                <div class="field">
                    <label for="smtp_secure">Xifratge</label>
                    <select class="select" id="smtp_secure" name="smtp_secure">
                        <option value="tls"<?= selectedIf($s['smtp_secure'] === 'tls') ?>>STARTTLS (recomanat)</option>
                        <option value="ssl"<?= selectedIf($s['smtp_secure'] === 'ssl') ?>>SSL/TLS</option>
                        <option value="none"<?= selectedIf($s['smtp_secure'] === 'none') ?>>Sense xifratge</option>
                    </select>
                </div>
                <div class="field">
                    <label for="smtp_user">Usuari</label>
                    <input class="input" type="text" id="smtp_user" name="smtp_user" value="<?= e($s['smtp_user']) ?>" autocomplete="off">
                </div>
                <div class="field">
                    <label for="smtp_pass">Contrasenya</label>
                    <input class="input" type="password" id="smtp_pass" name="smtp_pass"
                           placeholder="<?= $s['smtp_pass'] !== '' ? '••••••••••' : '' ?>" autocomplete="new-password">
                    <span class="field__hint">Deixa-ho buit per conservar la contrasenya desada.</span>
                </div>
                <div class="field">
                    <label for="smtp_batch_size">Correus per lot</label>
                    <input class="input" type="number" min="1" max="200" id="smtp_batch_size" name="smtp_batch_size" value="<?= e($s['smtp_batch_size']) ?>">
                    <span class="field__hint">Els comunicats s'envien per lots per no saturar el servidor.</span>
                </div>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="smtp_auth" value="1"<?= checkedIf($s['smtp_auth'] === '1') ?>>
                    <span>El servidor requereix autenticació</span>
                </label>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Remitent</h2></div></div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="smtp_from_email">Adreça del remitent</label>
                    <input class="input" type="email" id="smtp_from_email" name="smtp_from_email" value="<?= e($s['smtp_from_email']) ?>">
                </div>
                <div class="field">
                    <label for="smtp_from_name">Nom del remitent</label>
                    <input class="input" type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($s['smtp_from_name']) ?>">
                </div>
                <div class="field">
                    <label for="smtp_reply_to">Respondre a (opcional)</label>
                    <input class="input" type="email" id="smtp_reply_to" name="smtp_reply_to" value="<?= e($s['smtp_reply_to']) ?>">
                </div>
            </div>
            <div class="field">
                <label for="mail_footer">Peu dels correus</label>
                <textarea class="textarea" id="mail_footer" name="mail_footer" rows="2"><?= e($s['mail_footer']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Correus automàtics</h2>
                <p>Marcadors disponibles: <code>{{name}}</code>, <code>{{reference}}</code>,
                   <code>{{ticket_count}}</code>, <code>{{total}}</code>, <code>{{event_name}}</code>,
                   <code>{{event_date}}</code>, <code>{{event_organizer}}</code>, <code>{{refund_note}}</code>.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="field">
                <label for="mail_confirmation_subject">Assumpte · confirmació d'inscripció</label>
                <input class="input" type="text" id="mail_confirmation_subject" name="mail_confirmation_subject" value="<?= e($s['mail_confirmation_subject']) ?>">
            </div>
            <div class="field">
                <label for="mail_confirmation_body">Missatge · confirmació d'inscripció</label>
                <textarea class="textarea" id="mail_confirmation_body" name="mail_confirmation_body" rows="8"><?= e($s['mail_confirmation_body']) ?></textarea>
                <span class="field__hint">Les entrades en PDF s'adjunten automàticament a aquest correu.</span>
            </div>
            <div class="field">
                <label for="mail_cancellation_subject">Assumpte · anul·lació</label>
                <input class="input" type="text" id="mail_cancellation_subject" name="mail_cancellation_subject" value="<?= e($s['mail_cancellation_subject']) ?>">
            </div>
            <div class="field">
                <label for="mail_cancellation_body">Missatge · anul·lació</label>
                <textarea class="textarea" id="mail_cancellation_body" name="mail_cancellation_body" rows="6"><?= e($s['mail_cancellation_body']) ?></textarea>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar la configuració</button>
        </div>
    </div>
</form>

<div class="panel">
    <div class="panel__head"><div><h2>Provar l'enviament</h2><p>Comprova la configuració enviant un correu real.</p></div></div>
    <div class="panel__body">
        <form method="post" action="<?= e(url('/admin/configuracio/correu/prova')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <?= Csrf::field() ?>
            <div class="field" style="margin:0;flex:1 1 260px;">
                <label for="test_email">Enviar la prova a</label>
                <input class="input" type="email" id="test_email" name="test_email" value="<?= e(Auth::user()['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn--light" data-loading="Enviant…">Enviar el correu de prova</button>
        </form>
    </div>
</div>
