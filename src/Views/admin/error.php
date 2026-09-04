<div class="panel">
    <div class="panel__body" style="text-align:center;padding:50px 20px;">
        <p style="font-size:3rem;font-weight:900;color:var(--pdsh-primary);margin:0;"><?= (int) ($code ?? 500) ?></p>
        <p style="color:var(--pdsh-muted);"><?= e($message ?? 'Alguna cosa no ha anat bé.') ?></p>
        <a class="btn btn--light" href="<?= e(url('/admin')) ?>">Tornar al resum</a>
    </div>
</div>
