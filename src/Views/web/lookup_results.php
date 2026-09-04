<?php
use App\Services\TicketService;
?>

<div class="page-head">
    <div class="wrap wrap--narrow">
        <a class="page-head__back" href="<?= e(url('/')) ?>">← Tornar a l'inici</a>
        <h1>Les meves entrades</h1>
        <p>Inscripcions associades a <strong><?= e($email) ?></strong>.</p>
    </div>
</div>

<section class="section">
    <div class="wrap wrap--narrow">

        <?php if ($orders === []): ?>
            <div class="card"><div class="card__body">
                <p style="margin:0;">Aquesta adreça encara no té cap inscripció.</p>
            </div></div>
        <?php else: ?>
            <?php foreach ($orders as $order):
                $valid = array_filter($order['tickets'], static fn ($t) => $t['status'] === 'valid');
                $paid = in_array((string) $order['status'], ['paid', 'partially_refunded'], true);
            ?>
                <div class="card" style="margin-bottom:16px;">
                    <div class="card__body">
                        <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:baseline;">
                            <div>
                                <h3 style="margin-bottom:.2em;"><?= e($order['reference']) ?></h3>
                                <p style="margin:0;color:var(--pdsh-muted);font-size:.9rem;">
                                    <?= dt((string) $order['created_at'], 'd/m/Y') ?> ·
                                    <?= count($order['tickets']) ?> entrades ·
                                    <?= money((int) $order['total_cents']) ?>
                                </p>
                            </div>
                            <span class="badge <?= $paid ? 'badge--ok' : 'badge--muted' ?>">
                                <?= e(TicketService::statusLabel((string) $order['status'])) ?>
                            </span>
                        </div>

                        <div class="ticket-list" style="margin-top:16px;">
                            <?php foreach ($order['tickets'] as $ticket): ?>
                                <div class="ticket-row <?= $ticket['status'] === 'valid' ? '' : 'is-cancelled' ?>">
                                    <div class="ticket-row__main">
                                        <p class="ticket-row__name"><?= e($ticket['attendee_name'] ?: $order['name']) ?></p>
                                        <span class="ticket-row__type">
                                            <?= e($ticket['type_name']) ?>
                                            <?php if ($ticket['status'] !== 'valid'): ?>
                                                · <strong><?= e(TicketService::statusLabel((string) $ticket['status'])) ?></strong>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <span class="ticket-row__code"><?= e($ticket['code']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($paid): ?>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                                <a class="btn btn--primary btn--sm"
                                   href="<?= e(url('/comanda/' . $order['reference']) . '?t=' . $order['manage_token']) ?>">
                                    Gestionar aquesta inscripció
                                </a>
                                <?php if ($valid !== []): ?>
                                    <a class="btn btn--light btn--sm"
                                       href="<?= e(url('/comanda/' . $order['reference'] . '/pdf') . '?t=' . $order['manage_token']) ?>">
                                        Descarregar el PDF
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
