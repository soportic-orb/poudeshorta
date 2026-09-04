<?php use App\Core\Csrf; ?>

<div class="page-head">
    <div class="wrap wrap--narrow">
        <a class="page-head__back" href="<?= e(url('/')) ?>">← Tornar a l'inici</a>
        <h1>Les meves entrades</h1>
        <p>Introduïu l'adreça electrònica amb què us vau inscriure i us enviarem un enllaç
           per veure, descarregar o anul·lar les vostres entrades. No cal cap compte d'usuari.</p>
    </div>
</div>

<section class="section">
    <div class="wrap wrap--narrow">

        <div class="card">
            <div class="card__body">
                <form method="post" action="<?= e(url('/les-meves-entrades')) ?>" data-guard>
                    <?= Csrf::field() ?>
                    <div class="field">
                        <label for="email">Correu electrònic</label>
                        <input class="input" type="email" id="email" name="email"
                               placeholder="elmeu@correu.cat" autocomplete="email" required autofocus>
                    </div>
                    <button type="submit" class="btn btn--primary btn--block" data-loading="Enviant…">
                        Enviar-me l'enllaç
                    </button>
                </form>
            </div>
        </div>

        <p style="color:var(--pdsh-muted);font-size:.88rem;margin-top:18px;">
            Per seguretat, no confirmem si una adreça té inscripcions o no.
            L'enllaç que enviem caduca al cap d'una hora.
        </p>
    </div>
</section>
