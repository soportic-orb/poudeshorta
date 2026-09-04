<section class="section">
    <div class="wrap wrap--narrow">
        <div class="success-hero">
            <span class="success-hero__icon" aria-hidden="true">⏳</span>
            <h1>Estem confirmant el pagament</h1>
            <p>
                El vostre banc encara no ens ha confirmat l'operació. Sol trigar pocs segons.
                Quan es confirmi, us enviarem les entrades automàticament a
                <strong><?= e($order['email']) ?></strong>.
            </p>
        </div>

        <div class="card">
            <div class="card__body">
                <table class="summary-table">
                    <tr><th>Referència</th><td><?= e($order['reference']) ?></td></tr>
                    <tr><th>Import</th><td><?= money((int) $order['total_cents']) ?></td></tr>
                </table>
                <p style="margin:18px 0 0;color:var(--pdsh-muted);font-size:.92rem;">
                    No cal que torneu a pagar. Si al cap d'uns minuts no rebeu res, poseu-vos en contacte amb l'organització.
                </p>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:center;margin-top:22px;flex-wrap:wrap;">
            <button type="button" class="btn btn--primary" onclick="location.reload();">Actualitzar l'estat</button>
            <a class="btn btn--light" href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>
        </div>
    </div>
</section>
