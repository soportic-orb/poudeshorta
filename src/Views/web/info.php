<?php
use App\Core\Settings;
use App\Services\TicketService;

$deadline = TicketService::cancellationDeadline();
?>

<div class="page-head">
    <div class="wrap wrap--narrow">
        <a class="page-head__back" href="<?= e(url('/')) ?>">← Tornar a l'inici</a>
        <h1>Informació de l'esdeveniment</h1>
        <p>Tot el que cal saber sobre el <?= e(Settings::get('event_name')) ?>,
           les condicions de la inscripció i com anul·lar-la.</p>
    </div>
</div>

<section class="section">
    <div class="wrap wrap--narrow">

        <div class="card" style="margin-bottom:20px;">
            <div class="card__body">
                <h2 style="font-size:1.2rem;"><?= e(Settings::get('event_name')) ?></h2>
                <p><?= nl2br(e(Settings::get('event_description'))) ?></p>

                <table class="summary-table">
                    <tr><th>Data</th><td><?= e(Settings::get('event_date_text')) ?></td></tr>
                    <?php if (($place = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) !== ''): ?>
                        <tr>
                            <th>Lloc</th>
                            <td>
                                <?= e($place) ?>
                                <?php if (($map = (string) Settings::get('event_map_url')) !== ''): ?>
                                    <br><a href="<?= e($map) ?>" target="_blank" rel="noopener">Veure al mapa ↗</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr><th>Organitza</th><td><?= e(Settings::get('event_organizer')) ?></td></tr>
                    <?php if (($contact = (string) Settings::get('event_contact_email')) !== ''): ?>
                        <tr><th>Contacte</th><td><a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($highlights !== []): ?>
                    <h3 style="margin-top:22px;font-size:1.05rem;">Què hi trobareu</h3>
                    <ul class="includes">
                        <?php foreach ($highlights as $highlight): ?><li><span><?= e($highlight) ?></span></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card__body">
                <h2 style="font-size:1.2rem;">Anul·lacions i devolucions</h2>
                <p style="margin-bottom:0;"><?= nl2br(e($policy)) ?></p>
                <?php if ($deadline !== null): ?>
                    <p style="margin:12px 0 0;"><strong>Termini per anul·lar: <?= date('d/m/Y', $deadline) ?>.</strong></p>
                <?php endif; ?>
                <p style="margin:14px 0 0;color:var(--pdsh-muted);font-size:.92rem;">
                    Podeu tramitar l'anul·lació vosaltres mateixos des de
                    <a href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>.
                </p>
            </div>
        </div>

        <?php if (trim((string) $privacy) !== ''): ?>
            <div class="card">
                <div class="card__body">
                    <h2 style="font-size:1.2rem;">Protecció de dades</h2>
                    <p style="margin-bottom:0;"><?= nl2br(e($privacy)) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
