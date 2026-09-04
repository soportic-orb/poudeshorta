<?php
use App\Core\Csrf;
use App\Core\View;
use App\Services\TicketService;

$s = $settings;
$deadline = TicketService::cancellationDeadline();
?>

<?= View::partial('admin/_settings_tabs') ?>

<form method="post" action="<?= e(url('/admin/configuracio/anullacions')) ?>">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Qui pot anul·lar i fins quan</h2>
                <p>Aquests paràmetres decideixen si les persones inscrites poden anul·lar-se elles mateixes des del web.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="cancellation_enabled" value="1"<?= checkedIf($s['cancellation_enabled'] === '1') ?>>
                    <span><strong>Permetre l'anul·lació des del web públic</strong></span>
                </label>
                <span class="field__hint">Si ho desactiveu, només podreu anul·lar inscripcions des d'aquest panell.</span>
            </div>

            <div class="field">
                <label class="check">
                    <input type="checkbox" name="cancellation_allow_partial" value="1"<?= checkedIf($s['cancellation_allow_partial'] === '1') ?>>
                    <span>Permetre anul·lar només algunes entrades d'una mateixa comanda</span>
                </label>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="cancellation_deadline_days">Termini: dies abans de l'esdeveniment</label>
                    <input class="input" type="number" min="0" id="cancellation_deadline_days" name="cancellation_deadline_days"
                           value="<?= e($s['cancellation_deadline_days']) ?>">
                    <span class="field__hint">Calculat sobre la data exacta de l'esdeveniment. 0 = sense límit per dies.</span>
                </div>
                <div class="field">
                    <label for="cancellation_deadline_date">O bé una data límit fixa</label>
                    <input class="input" type="date" id="cancellation_deadline_date" name="cancellation_deadline_date"
                           value="<?= e($s['cancellation_deadline_date'] !== '' ? date('Y-m-d', (int) strtotime((string) $s['cancellation_deadline_date'])) : '') ?>">
                    <span class="field__hint">Si l'ompliu, té prioritat sobre els dies.</span>
                </div>
            </div>

            <?php if ($deadline !== null): ?>
                <div class="alert alert--info">
                    <span aria-hidden="true">ℹ️</span>
                    <span>Amb la configuració actual, el termini per anul·lar acaba el
                        <strong><?= date('d/m/Y', $deadline) ?></strong>.</span>
                </div>
            <?php else: ?>
                <div class="alert alert--warning">
                    <span aria-hidden="true">⚠️</span>
                    <span>Ara mateix no hi ha cap termini: es podrà anul·lar en qualsevol moment.
                        Indiqueu una data límit o els dies previs (i la data exacta de l'esdeveniment).</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Devolucions</h2></div></div>
        <div class="panel__body">
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="cancellation_refund" value="1"<?= checkedIf($s['cancellation_refund'] === '1') ?>>
                    <span><strong>Retornar l'import automàticament amb Stripe</strong></span>
                </label>
                <span class="field__hint">La devolució es fa al mateix mitjà de pagament i pot trigar uns dies hàbils.</span>
            </div>

            <div class="field" style="max-width:300px;">
                <label for="cancellation_fee_percent">Despeses de gestió retingudes (%)</label>
                <input class="input" type="number" min="0" max="100" step="0.5"
                       id="cancellation_fee_percent" name="cancellation_fee_percent" value="<?= e($s['cancellation_fee_percent']) ?>">
                <span class="field__hint">0 = es retorna l'import íntegre.</span>
            </div>

            <div class="field">
                <label for="cancellation_policy_text">Text de la política d'anul·lacions</label>
                <textarea class="textarea" id="cancellation_policy_text" name="cancellation_policy_text" rows="5"><?= e($s['cancellation_policy_text']) ?></textarea>
                <span class="field__hint">Es mostra al web públic i a la pantalla de gestió de la inscripció.</span>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar la política</button>
        </div>
    </div>
</form>
