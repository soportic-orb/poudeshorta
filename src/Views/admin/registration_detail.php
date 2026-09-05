<?php
use App\Core\Csrf;
use App\Core\Settings;
use App\Services\TicketService;

$valid = array_values(array_filter($tickets, static fn ($t) => $t['status'] === 'valid'));
?>

<p><a href="<?= e(url('/admin/inscripcions')) ?>">← Tornar al llistat</a></p>

<div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start;">

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2><?= e($order['reference']) ?></h2>
                <p><?= dt((string) $order['created_at']) ?></p>
            </div>
            <?php
            $badge = match ((string) $order['status']) {
                'paid' => 'badge--ok',
                'pending' => 'badge--warn',
                default => 'badge--danger',
            };
            ?>
            <span class="badge <?= $badge ?>"><?= e(TicketService::statusLabel((string) $order['status'])) ?></span>
        </div>
        <div class="panel__body">
            <dl class="kv">
                <dt>Nom</dt><dd><?= e(trim((string) $order['name'] . ' ' . (string) $order['surname'])) ?></dd>
                <dt>Correu</dt><dd><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></dd>
                <dt>Telèfon</dt><dd><?= e($order['phone'] ?: '—') ?></dd>
                <dt>Import</dt><dd><?= money((int) $order['total_cents']) ?></dd>
                <?php if ((int) $order['refunded_cents'] > 0): ?>
                    <dt>Retornat</dt><dd style="color:var(--pdsh-danger);"><?= money((int) $order['refunded_cents']) ?></dd>
                <?php endif; ?>
                <dt>Pagament</dt><dd><?= dt($order['paid_at']) ?></dd>
                <?php if (!empty($order['stripe_payment_intent'])): ?>
                    <dt>Stripe</dt>
                    <dd style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem;">
                        <a href="https://dashboard.stripe.com/payments/<?= e($order['stripe_payment_intent']) ?>" target="_blank" rel="noopener">
                            <?= e($order['stripe_payment_intent']) ?> ↗
                        </a>
                    </dd>
                <?php endif; ?>
                <dt>IP</dt><dd style="font-size:.85rem;color:var(--pdsh-muted);"><?= e($order['ip'] ?: '—') ?></dd>
            </dl>
        </div>
        <div class="panel__foot">
            <?php if (in_array((string) $order['status'], ['paid', 'partially_refunded'], true) && $valid !== []): ?>
                <a class="btn btn--light btn--sm" href="<?= e(url('/admin/inscripcions/' . $order['id'] . '/pdf')) ?>" target="_blank" rel="noopener">
                    Veure les entrades
                </a>
                <form method="post" action="<?= e(url('/admin/inscripcions/' . $order['id'] . '/reenviar')) ?>"
                      data-confirm="Reenviar les entrades a <?= e($order['email']) ?>?">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--primary btn--sm">Reenviar per correu</button>
                </form>
            <?php endif; ?>
            <a class="btn btn--light btn--sm"
               href="<?= e(url('/comanda/' . $order['reference']) . '?t=' . $order['manage_token']) ?>"
               target="_blank" rel="noopener">Veure com el client ↗</a>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Notes internes</h2><p>Només visibles al panell.</p></div></div>
        <div class="panel__body">
            <form method="post" action="<?= e(url('/admin/inscripcions/' . $order['id'] . '/nota')) ?>">
                <?= Csrf::field() ?>
                <div class="field">
                    <label class="visually-hidden" for="notes">Notes</label>
                    <textarea class="textarea" id="notes" name="notes" rows="5"
                              placeholder="Al·lèrgies, taula assignada, incidències…"><?= e($order['notes']) ?></textarea>
                </div>
                <button type="submit" class="btn btn--light btn--sm">Desar la nota</button>
            </form>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div><h2>Entrades (<?= count($tickets) ?>)</h2></div>
    </div>

    <form method="post" action="<?= e(url('/admin/inscripcions/' . $order['id'] . '/anullar')) ?>"
          data-confirm="Anul·lar les entrades seleccionades? Si hi ha devolució, es tramitarà a Stripe.">
        <?= Csrf::field() ?>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th style="width:40px;"></th><th>Codi</th><th>Assistent</th><th>Tipus</th><th>Extres</th><th>Estat</th><th class="num">Import</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket):
                        $extra = json_decode((string) $ticket['extra_json'], true);
                    ?>
                        <tr>
                            <td>
                                <?php if ($ticket['status'] === 'valid'): ?>
                                    <input type="checkbox" name="tickets[]" value="<?= (int) $ticket['id'] ?>"
                                           aria-label="Seleccionar l'entrada <?= e($ticket['code']) ?>">
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= e($ticket['code']) ?></td>
                            <td><?= e($ticket['attendee_name'] ?: '—') ?></td>
                            <td><?= e($ticket['type_name']) ?></td>
                            <td style="font-size:.82rem;color:var(--pdsh-muted);">
                                <?php if (is_array($extra) && $extra !== []): ?>
                                    <?php foreach ($extra as $label => $value): ?>
                                        <?= e($label) ?>: <?= e(is_array($value) ? implode(', ', $value) : $value) ?><br>
                                    <?php endforeach; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $ticket['status'] === 'valid' ? 'badge--ok' : ($ticket['status'] === 'used' ? 'badge--muted' : 'badge--danger') ?>">
                                    <?= e(TicketService::statusLabel((string) $ticket['status'])) ?>
                                </span>
                                <?php if (!empty($ticket['checked_in_at'])): ?>
                                    <br><span style="font-size:.78rem;color:var(--pdsh-olive);">
                                        ✓ <?= dt((string) $ticket['checked_in_at'], 'd/m H:i') ?>
                                        <?= $ticket['checked_in_by'] ? '· ' . e($ticket['checked_in_by']) : '' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= money((int) $ticket['price_cents']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($valid !== []): ?>
            <div class="panel__foot">
                <button type="submit" class="btn btn--danger btn--sm">Anul·lar les seleccionades</button>
                <span style="font-size:.85rem;color:var(--pdsh-muted);">
                    Si no en selecciones cap, s'anul·laran totes les vàlides.
                    <?php if (\App\Core\Settings::bool('cancellation_refund', true)): ?>
                        La devolució es tramita automàticament a Stripe
                        <?php $fee = (float) Settings::get('cancellation_fee_percent', '0'); ?>
                        <?= $fee > 0 ? '(retenint un ' . e(rtrim(rtrim(number_format($fee, 2, ',', '.'), '0'), ',')) . '%)' : '' ?>.
                    <?php else: ?>
                        Les devolucions automàtiques estan desactivades.
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($refunds !== []): ?>
    <div class="panel">
        <div class="panel__head"><div><h2>Devolucions</h2></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Data</th><th class="num">Import</th><th>Motiu</th><th>Origen</th><th>Estat</th><th>Stripe</th></tr></thead>
                <tbody>
                    <?php foreach ($refunds as $refund): ?>
                        <tr>
                            <td><?= dt((string) $refund['created_at']) ?></td>
                            <td class="num"><?= money((int) $refund['amount_cents']) ?></td>
                            <td><?= e($refund['reason'] ?: '—') ?></td>
                            <td><?= e($refund['initiated_by']) ?></td>
                            <td><span class="badge"><?= e($refund['status']) ?></span></td>
                            <td class="mono" style="font-size:.78rem;"><?= e($refund['stripe_refund_id'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
