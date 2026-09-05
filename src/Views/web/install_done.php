<?php $heading = 'Instal·lació completada'; ?>

<div style="text-align:center;padding:10px 0 6px;">
    <span style="font-size:3.4rem;display:block;" aria-hidden="true">🎉</span>
    <h2 style="margin:12px 0 6px;">Tot a punt!</h2>
    <p style="color:var(--pdsh-muted);">
        La plataforma d'inscripcions ja està instal·lada. Entra al Panell de Gestió
        per configurar l'esdeveniment, les claus de Stripe i el servidor de correu.
    </p>
</div>

<a class="btn btn--primary btn--block btn--lg" href="<?= e($loginUrl) ?>">Entrar al Panell de Gestió</a>

<div class="alert alert--warning" style="margin-top:22px;">
    <span aria-hidden="true">⚠️</span>
    <span>
        <strong>Passos següents recomanats:</strong>
        configura les claus de Stripe i el webhook, el servidor SMTP, els tipus d'inscripció
        i la política d'anul·lacions. Programa també la tasca cron
        (<code>bin/cron.php</code>) cada 5 minuts.
    </span>
</div>
