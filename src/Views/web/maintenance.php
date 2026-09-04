<?php use App\Core\Settings; ?>
<section class="section">
    <div class="wrap wrap--narrow" style="text-align:center;">
        <span style="font-size:3.6rem;display:block;margin-bottom:12px;" aria-hidden="true">🛠️</span>
        <h1>Tornem de seguida</h1>
        <p style="color:var(--pdsh-muted);max-width:52ch;margin:0 auto 24px;">
            Estem fent tasques de manteniment a la plataforma d'inscripcions del
            <strong><?= e(Settings::get('event_name')) ?></strong>. Torneu-ho a provar d'aquí a uns minuts.
        </p>
        <?php if (($contact = (string) Settings::get('event_contact_email')) !== ''): ?>
            <p style="font-size:.92rem;">Si és urgent: <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a></p>
        <?php endif; ?>
    </div>
</section>
