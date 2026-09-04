<?php
$labels = ['draft' => 'Esborrany', 'queued' => 'A la cua', 'sending' => 'Enviant-se', 'sent' => 'Enviat', 'cancelled' => 'Cancel·lat'];
?>

<?php if (!$smtpReady): ?>
    <div class="alert alert--warning">
        <span aria-hidden="true">⚠️</span>
        <span>Encara no heu configurat el servidor SMTP. Els comunicats no es podran enviar fins que ho feu a
            <a href="<?= e(url('/admin/configuracio/correu')) ?>">Configuració → Correu</a>.</span>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel__head">
        <div>
            <h2>Comunicats als inscrits</h2>
            <p>Escriviu un missatge i envieu-lo per correu a totes les persones inscrites.
                <?= $pending > 0 ? '<strong>' . (int) $pending . ' correus pendents a la cua.</strong>' : '' ?></p>
        </div>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/comunicacions/nova')) ?>">+ Nou comunicat</a>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Data</th><th>Assumpte</th><th class="num">Destinataris</th><th class="num">Enviats</th><th class="num">Errors</th><th>Estat</th></tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= dt((string) $campaign['created_at'], 'd/m/y H:i') ?></td>
                        <td>
                            <a href="<?= e(url('/admin/comunicacions/' . $campaign['id'])) ?>"><?= e($campaign['subject']) ?></a>
                            <?php if (!empty($campaign['created_by'])): ?>
                                <br><span style="color:var(--pdsh-muted);font-size:.82rem;">per <?= e($campaign['created_by']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $campaign['total'] ?></td>
                        <td class="num"><?= (int) $campaign['sent_count'] ?></td>
                        <td class="num"><?= (int) $campaign['failed_count'] > 0 ? '<span style="color:var(--pdsh-danger);">' . (int) $campaign['failed_count'] . '</span>' : '0' ?></td>
                        <td>
                            <span class="badge <?= $campaign['status'] === 'sent' ? 'badge--ok' : ($campaign['status'] === 'draft' ? 'badge--muted' : 'badge--warn') ?>">
                                <?= e($labels[$campaign['status']] ?? $campaign['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($campaigns === []): ?>
                    <tr><td colspan="6" class="empty">
                        <span class="empty__icon" aria-hidden="true">✉️</span>
                        Encara no heu enviat cap comunicat.
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
