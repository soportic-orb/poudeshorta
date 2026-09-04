<?php
use App\Services\TicketService;

$maxDaily = max(1, max(array_column($daily, 'comandes')));
?>

<div class="stat-grid">
    <div class="stat stat--primary">
        <div class="stat__label">Entrades venudes</div>
        <div class="stat__value"><?= (int) $stats['tickets'] ?></div>
        <div class="stat__hint"><?= (int) $stats['paidOrders'] ?> inscripcions confirmades</div>
    </div>
    <div class="stat">
        <div class="stat__label">Recaptació neta</div>
        <div class="stat__value"><?= money((int) $stats['revenue']) ?></div>
        <div class="stat__hint">
            <?= (int) $stats['refunded'] > 0 ? money((int) $stats['refunded']) . ' retornats' : 'Sense devolucions' ?>
        </div>
    </div>
    <div class="stat stat--accent">
        <div class="stat__label">Darrers 7 dies</div>
        <div class="stat__value"><?= (int) $stats['last7'] ?></div>
        <div class="stat__hint">inscripcions noves</div>
    </div>
    <div class="stat stat--olive">
        <div class="stat__label">Validades a l'accés</div>
        <div class="stat__value"><?= (int) $stats['checkedIn'] ?></div>
        <div class="stat__hint"><?= (int) $stats['cancelled'] ?> entrades anul·lades</div>
    </div>
</div>

<div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));align-items:start;">

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Abans d'obrir les inscripcions</h2>
                <p>Repàs ràpid de la configuració necessària.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="checklist">
                <?php foreach ($checklist as $item): ?>
                    <a class="checklist__item" href="<?= e(url($item['url'])) ?>">
                        <span class="checklist__state <?= $item['ok'] ? 'is-ok' : (!empty($item['optional']) ? 'is-optional' : 'is-todo') ?>">
                            <?= $item['ok'] ? '✓' : (!empty($item['optional']) ? '·' : '!') ?>
                        </span>
                        <span class="checklist__text">
                            <strong><?= e($item['label']) ?></strong>
                            <span><?= e($item['hint']) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Inscripcions per tipus</h2>
                <p>Places venudes i recaptació de cada tipus.</p>
            </div>
            <a class="btn btn--light btn--sm" href="<?= e(url('/admin/tipus-inscripcio')) ?>">Gestionar</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Tipus</th><th class="num">Venudes</th><th class="num">Places</th><th class="num">Import</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($byType as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="num"><strong><?= (int) $row['sold'] ?></strong></td>
                            <td class="num"><?= $row['quota'] === null ? '∞' : (int) $row['quota'] ?></td>
                            <td class="num"><?= money((int) $row['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($byType === []): ?>
                        <tr><td colspan="4" class="empty">Encara no hi ha cap tipus d'inscripció.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Inscripcions dels darrers 14 dies</h2>
            <p>Nombre de comandes confirmades per dia.</p>
        </div>
    </div>
    <div class="panel__body">
        <div class="chart">
            <?php foreach ($daily as $day): ?>
                <div class="chart__bar" title="<?= e($day['etiqueta']) ?>: <?= (int) $day['comandes'] ?> inscripcions (<?= money((int) $day['import']) ?>)">
                    <span class="chart__track">
                        <span class="chart__fill" style="height:<?= $day['comandes'] > 0 ? max(4, (int) round(($day['comandes'] / $maxDaily) * 100)) : 2 ?>%;"></span>
                    </span>
                    <span class="chart__label"><?= e($day['etiqueta']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Darreres inscripcions</h2>
            <?php if ($pendingMail > 0): ?>
                <p><?= (int) $pendingMail ?> correus pendents d'enviar a la cua.</p>
            <?php endif; ?>
        </div>
        <a class="btn btn--light btn--sm" href="<?= e(url('/admin/inscripcions')) ?>">Veure-les totes</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Data</th><th>Referència</th><th>Persona</th><th class="num">Entrades</th><th class="num">Import</th><th>Estat</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $order): ?>
                    <tr>
                        <td><?= dt((string) $order['created_at'], 'd/m H:i') ?></td>
                        <td class="mono"><a href="<?= e(url('/admin/inscripcions/' . $order['id'])) ?>"><?= e($order['reference']) ?></a></td>
                        <td>
                            <?= e(trim((string) $order['name'] . ' ' . (string) $order['surname'])) ?><br>
                            <span style="color:var(--pdsh-muted);font-size:.83rem;"><?= e($order['email']) ?></span>
                        </td>
                        <td class="num"><?= (int) $order['ticket_count'] ?></td>
                        <td class="num"><?= money((int) $order['total_cents']) ?></td>
                        <td>
                            <?php
                            $badge = match ((string) $order['status']) {
                                'paid' => 'badge--ok',
                                'pending' => 'badge--warn',
                                'cancelled', 'refunded', 'failed' => 'badge--danger',
                                default => 'badge--muted',
                            };
                            ?>
                            <span class="badge <?= $badge ?>"><?= e(TicketService::statusLabel((string) $order['status'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recent === []): ?>
                    <tr><td colspan="6" class="empty">
                        <span class="empty__icon" aria-hidden="true">🎟</span>
                        Encara no hi ha cap inscripció.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
