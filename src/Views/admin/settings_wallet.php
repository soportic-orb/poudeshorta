<?php
use App\Core\Csrf;
use App\Core\View;
use App\Services\TicketService;

$s = $settings;
?>

<?= View::partial('admin/_settings_tabs') ?>

<div class="alert alert--info">
    <span aria-hidden="true">ℹ️</span>
    <span>Els passis de wallet són <strong>opcionals</strong>. Si no els configureu, els botons no apareixeran
        i les entrades seguiran funcionant amb el PDF i el codi QR.</span>
</div>

<form method="post" action="<?= e(url('/admin/configuracio/wallet')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__body">
            <label class="check">
                <input type="checkbox" name="wallet_enabled" value="1"<?= checkedIf($s['wallet_enabled'] === '1') ?>>
                <span><strong>Activar els passis per a Apple Wallet i Google Wallet</strong></span>
            </label>

            <div class="field" style="margin-top:18px;max-width:340px;">
                <label for="wallet_expire_hours">Els passis caduquen al cap de</label>
                <input class="input" type="number" id="wallet_expire_hours" name="wallet_expire_hours"
                       min="0" max="8760" step="1" value="<?= e($s['wallet_expire_hours']) ?>">
                <p class="field__hint">
                    Hores a comptar des de la data de l'esdeveniment.
                    <?php if (($caduca = TicketService::walletExpiry()) !== null): ?>
                        Amb la data actual, els passis caducaran el
                        <strong><?= date('d/m/Y', $caduca) ?> a les <?= date('H:i', $caduca) ?></strong>.
                    <?php else: ?>
                        Cal omplir la data de l'esdeveniment a Configuració → Esdeveniment
                        perquè els passis puguin caducar.
                    <?php endif; ?>
                </p>
            </div>

            <div class="alert alert--info" style="margin:16px 0 0;">
                <span aria-hidden="true">ℹ️</span>
                <span>
                    Ni l'Apple Wallet ni el Google Wallet deixen esborrar un passi del telèfon
                    de ningú: això només ho pot fer la persona que el té. El que sí que fem és
                    marcar-los com a <strong>caducats</strong> passat aquest temps, i llavors el
                    mòbil els treu de la llista de passis actius i els arracona als caducats.
                </span>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Apple Wallet</h2>
                <p>Cal un compte de desenvolupador d'Apple i un certificat de tipus «Pass Type ID».</p>
            </div>
            <span class="badge <?= $appleOk ? 'badge--ok' : 'badge--warn' ?>"><?= e($appleHint) ?></span>
        </div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="apple_pass_type_id">Pass Type ID</label>
                    <input class="input" type="text" id="apple_pass_type_id" name="apple_pass_type_id"
                           value="<?= e($s['apple_pass_type_id']) ?>" placeholder="pass.online.poudeshorta.entrada">
                </div>
                <div class="field">
                    <label for="apple_team_id">Team ID</label>
                    <input class="input" type="text" id="apple_team_id" name="apple_team_id" value="<?= e($s['apple_team_id']) ?>">
                </div>
                <div class="field">
                    <label for="apple_organization">Nom de l'organització</label>
                    <input class="input" type="text" id="apple_organization" name="apple_organization" value="<?= e($s['apple_organization']) ?>">
                </div>
                <div class="field">
                    <label for="apple_key_password">Contrasenya del certificat</label>
                    <input class="input" type="password" id="apple_key_password" name="apple_key_password"
                           placeholder="<?= $s['apple_key_password'] !== '' ? '••••••••' : '' ?>" autocomplete="new-password">
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="apple_cert">Certificat del pass (.p12 o .pem)</label>
                    <input class="input" type="file" id="apple_cert" name="apple_cert" accept=".p12,.pfx,.pem,.cer,.crt">
                    <span class="field__hint"><?= $s['apple_cert_path'] !== '' ? 'Desat: ' . e(basename((string) $s['apple_cert_path'])) : 'Cap fitxer desat.' ?></span>
                </div>
                <div class="field">
                    <label for="apple_key">Clau privada (.pem) — només si el certificat no és .p12</label>
                    <input class="input" type="file" id="apple_key" name="apple_key" accept=".pem,.key">
                    <span class="field__hint"><?= $s['apple_key_path'] !== '' ? 'Desada: ' . e(basename((string) $s['apple_key_path'])) : 'Cap fitxer desat.' ?></span>
                </div>
                <div class="field">
                    <label for="apple_wwdr">Certificat WWDR d'Apple</label>
                    <input class="input" type="file" id="apple_wwdr" name="apple_wwdr" accept=".pem,.cer,.crt">
                    <span class="field__hint"><?= $s['apple_wwdr_path'] !== '' ? 'Desat: ' . e(basename((string) $s['apple_wwdr_path'])) : 'Descarregueu-lo del portal de desenvolupadors d\'Apple.' ?></span>
                </div>
            </div>

            <div class="alert alert--info" style="margin-bottom:0;">
                <span aria-hidden="true">🔒</span>
                <span>Els certificats es desen a <code>storage/certificates/</code>, fora del directori públic,
                      amb permisos restringits. Si pugeu un <code>.p12</code>, es converteix automàticament al
                      format que fa servir el servidor i la contrasenya deixa de ser necessària.</span>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Google Wallet</h2>
                <p>Cal un compte a la Google Wallet Console i un compte de servei de Google Cloud.</p>
            </div>
            <span class="badge <?= $googleOk ? 'badge--ok' : 'badge--warn' ?>"><?= e($googleHint) ?></span>
        </div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="google_issuer_id">Issuer ID</label>
                    <input class="input" type="text" id="google_issuer_id" name="google_issuer_id"
                           value="<?= e($s['google_issuer_id']) ?>" placeholder="3388000000012345678">
                </div>
                <div class="field">
                    <label for="google_class_suffix">Identificador de la classe</label>
                    <input class="input" type="text" id="google_class_suffix" name="google_class_suffix" value="<?= e($s['google_class_suffix']) ?>">
                    <span class="field__hint">Un nom curt sense espais, per exemple <code>sopar_2026</code>.</span>
                </div>
            </div>

            <div class="field">
                <label for="google_json">Fitxer JSON del compte de servei</label>
                <input class="input" type="file" id="google_json" name="google_json" accept=".json,application/json">
                <span class="field__hint">
                    <?= $s['google_service_account_json'] !== '' ? 'Ja hi ha un compte de servei desat.' : 'Cap compte de servei desat.' ?>
                    Recordeu autoritzar l'adreça del compte de servei a la Google Wallet Console.
                </span>
            </div>

            <details>
                <summary style="cursor:pointer;font-weight:700;font-size:.92rem;">O enganxar-ne el contingut manualment</summary>
                <div class="field" style="margin-top:12px;">
                    <label class="visually-hidden" for="google_service_account_json">JSON del compte de servei</label>
                    <textarea class="textarea" id="google_service_account_json" name="google_service_account_json" rows="6"
                              placeholder='{"type":"service_account", ...}'></textarea>
                </div>
            </details>

            <div class="alert <?= \App\Services\GoogleWallet::classRegistered() ? 'alert--success' : 'alert--warning' ?>"
                 style="margin:18px 0 0;">
                <span aria-hidden="true"><?= \App\Services\GoogleWallet::classRegistered() ? '✅' : '⚠️' ?></span>
                <span>
                    <?php if (\App\Services\GoogleWallet::classRegistered()): ?>
                        <strong>La classe de l'esdeveniment ja està creada</strong>
                        (<code><?= e(\App\Services\GoogleWallet::classId()) ?></code>).
                        Els enllaços de cada entrada són curts i no arriben al límit de Google.
                    <?php else: ?>
                        <strong>Encara no heu creat la classe de l'esdeveniment.</strong>
                        Fins que no ho feu, cada enllaç ha de portar-hi totes les dades i pot superar
                        el màxim de 1800 caràcters que admet Google, amb la qual cosa el passi no es desaria.
                        Deseu la configuració i premeu el botó de sota.
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar la configuració dels passis</button>
        </div>
    </div>
</form>

<form method="post" action="<?= e(url('/admin/configuracio/wallet/prova')) ?>">
    <?= Csrf::field() ?>
    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Comprovar la configuració dels passis</h2>
                <p>Prova l'Apple Wallet i el Google Wallet amb dades fictícies. Feu-ho abans d'obrir les inscripcions.</p>
            </div>
        </div>
        <div class="panel__body">
            <p style="margin:0 0 16px;color:var(--pdsh-muted);font-size:.92rem;">
                Comprova que l'Apple Wallet pot signar passis amb els vostres certificats i que l'enllaç del
                Google Wallet es genera i cap dins del límit de Google. No es crea cap entrada ni s'envia res.
            </p>
            <button type="submit" class="btn btn--light" data-loading="Comprovant…">
                Comprovar la configuració
            </button>
        </div>
    </div>
</form>

<form method="post" action="<?= e(url('/admin/configuracio/wallet/classe-google')) ?>">
    <?= Csrf::field() ?>
    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Classe de l'esdeveniment al Google Wallet</h2>
                <p>Es crea una sola vegada. Torneu-hi si canvieu el nom, la data o el lloc de l'esdeveniment.</p>
            </div>
        </div>
        <div class="panel__body">
            <p style="margin:0 0 16px;color:var(--pdsh-muted);font-size:.92rem;">
                La classe conté les dades comunes a totes les entrades (nom de l'esdeveniment, data, lloc i colors).
                Creant-la al vostre compte, l'enllaç de cada entrada només ha de portar les dades de la persona
                i queda ben per sota del límit que Google admet.
            </p>
            <button type="submit" class="btn btn--light" data-loading="Parlant amb Google…">
                <?= \App\Services\GoogleWallet::classRegistered() ? 'Actualitzar la classe' : 'Crear la classe al Google Wallet' ?>
            </button>
        </div>
    </div>
</form>
