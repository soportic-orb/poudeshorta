<?php
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\View;

$s = $settings;
$mask = static fn (string $value): string => $value === '' ? '' : '••••••••••••' . substr($value, -4);
$live = $s['stripe_mode'] === 'live';
?>

<?= View::partial('admin/_settings_tabs') ?>

<?php if (!Settings::stripeConfigured()): ?>
    <div class="alert alert--warning">
        <span aria-hidden="true">⚠️</span>
        <span>Encara no hi ha claus de Stripe per al mode <strong><?= $live ? 'real' : 'de proves' ?></strong>.
              Fins que no les configureu, ningú no podrà pagar.</span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/configuracio/pagaments')) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head"><div><h2>Mode de funcionament</h2></div></div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="stripe_mode">Mode</label>
                    <select class="select" id="stripe_mode" name="stripe_mode">
                        <option value="test"<?= selectedIf(!$live) ?>>Proves (test) — no es cobra res de veritat</option>
                        <option value="live"<?= selectedIf($live) ?>>Real (live) — cobraments reals</option>
                    </select>
                </div>
                <div class="field">
                    <label for="currency">Moneda</label>
                    <select class="select" id="currency" name="currency">
                        <?php foreach (['EUR' => 'Euro (€)', 'USD' => 'Dòlar ($)', 'GBP' => 'Lliura (£)'] as $code => $label): ?>
                            <option value="<?= $code ?>"<?= selectedIf($s['currency'] === $code) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="stripe_locale">Idioma de la passarel·la</label>
                    <select class="select" id="stripe_locale" name="stripe_locale">
                        <?php
                        $idiomes = [
                            'auto'   => 'Automàtic segons el navegador (recomanat)',
                            'es'     => 'Castellà',
                            'en'     => 'Anglès',
                            'fr'     => 'Francès',
                            'it'     => 'Italià',
                            'de'     => 'Alemany',
                            'pt'     => 'Portuguès',
                        ];
                        foreach ($idiomes as $codi => $nom): ?>
                            <option value="<?= e($codi) ?>"<?= selectedIf(($s['stripe_locale'] ?? 'auto') === $codi) ?>>
                                <?= e($nom) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint">
                        Stripe no ofereix la passarel·la en català. Amb «automàtic», qui pagui la veurà
                        en l'idioma del seu navegador; als navegadors en català sol sortir en castellà.
                    </span>
                </div>

            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Claus de proves</h2>
                <p>Les trobareu al tauler de Stripe amb el mode de proves activat.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="field">
                <label for="stripe_test_pk">Clau publicable (pk_test_…)</label>
                <input class="input" type="text" id="stripe_test_pk" name="stripe_test_pk" value="<?= e($s['stripe_test_pk']) ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="stripe_test_sk">Clau secreta (sk_test_…)</label>
                <input class="input" type="password" id="stripe_test_sk" name="stripe_test_sk"
                       placeholder="<?= e($mask((string) $s['stripe_test_sk'])) ?>" autocomplete="off">
                <span class="field__hint">Deixeu-ho buit per conservar la clau desada.</span>
            </div>
            <div class="field">
                <label for="stripe_test_wh_secret">Secret del webhook (whsec_…)</label>
                <input class="input" type="password" id="stripe_test_wh_secret" name="stripe_test_wh_secret"
                       placeholder="<?= e($mask((string) $s['stripe_test_wh_secret'])) ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Claus reals</h2>
                <p>Activeu el mode real només quan hàgiu fet una compra de prova completa.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="field">
                <label for="stripe_live_pk">Clau publicable (pk_live_…)</label>
                <input class="input" type="text" id="stripe_live_pk" name="stripe_live_pk" value="<?= e($s['stripe_live_pk']) ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="stripe_live_sk">Clau secreta (sk_live_…)</label>
                <input class="input" type="password" id="stripe_live_sk" name="stripe_live_sk"
                       placeholder="<?= e($mask((string) $s['stripe_live_sk'])) ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="stripe_live_wh_secret">Secret del webhook (whsec_…)</label>
                <input class="input" type="password" id="stripe_live_wh_secret" name="stripe_live_wh_secret"
                       placeholder="<?= e($mask((string) $s['stripe_live_wh_secret'])) ?>" autocomplete="off">
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar la configuració</button>
        </div>
    </div>
</form>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Configurar el webhook a Stripe</h2>
            <p>És imprescindible: sense el webhook, un pagament pot quedar sense confirmar si el navegador es tanca.</p>
        </div>
    </div>
    <div class="panel__body">
        <ol style="padding-left:20px;line-height:1.9;">
            <li>Entreu al tauler de Stripe → <strong>Developers → Webhooks → Add endpoint</strong>.</li>
            <li>Enganxeu aquesta URL de destinació:
                <div class="copy-row" style="margin:8px 0;">
                    <input class="input" type="text" id="webhook-url" value="<?= e($webhookUrl) ?>" readonly>
                    <button type="button" class="btn btn--light btn--sm" data-copy-target="#webhook-url">Copiar</button>
                </div>
            </li>
            <li>Seleccioneu aquests esdeveniments:
                <code>checkout.session.completed</code>, <code>checkout.session.expired</code>,
                <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>
                i <code>charge.refunded</code>.
            </li>
            <li>Copieu el <strong>Signing secret</strong> (<code>whsec_…</code>) i enganxeu-lo al camp corresponent d'aquesta pàgina.</li>
        </ol>

        <div class="alert alert--info" style="margin-bottom:0;">
            <span aria-hidden="true">ℹ️</span>
            <span>Per provar-ho: amb el mode de proves, feu una compra amb la targeta
                <code>4242 4242 4242 4242</code>, qualsevol data futura i qualsevol CVC.</span>
        </div>
    </div>
</div>
