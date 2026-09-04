<section class="section">
    <div class="wrap wrap--narrow" style="text-align:center;">
        <p style="font-size:4rem;font-weight:900;color:var(--pdsh-primary);margin:0;line-height:1;"><?= (int) ($code ?? 500) ?></p>
        <h1><?= e($title ?? 'Alguna cosa no ha anat bé') ?></h1>
        <p style="color:var(--pdsh-muted);max-width:48ch;margin:0 auto 26px;"><?= e($message ?? '') ?></p>
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Tornar a l'inici</a>
    </div>
</section>
