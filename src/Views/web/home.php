<?php
use App\Core\Csrf;
use App\Core\Settings;
use App\Services\TicketService;

$poster = (string) Settings::get('event_poster');
$maxTotal = (int) Settings::get('max_tickets_order', 10);

// Imatge de fons de la capçalera, si se n'ha configurat cap.
$heroImage = trim((string) Settings::get('hero_image'));
$heroStyle = '';
if ($heroImage !== '') {
    $overlay = max(0, min(90, (int) Settings::get('hero_overlay', 55)));
    $focus   = in_array((string) Settings::get('hero_focus'), ['top', 'center', 'bottom'], true)
        ? (string) Settings::get('hero_focus')
        : 'center';

    $heroStyle = sprintf(
        'style="--hero-image:url(%s);--hero-overlay:%d%%;--hero-focus:center %s;"',
        "'" . e(url($heroImage)) . "'",
        $overlay,
        $focus
    );
}

// Dies que falten, si s'ha indicat la data exacta de l'esdeveniment.
$daysLeft = null;
$eventDate = trim((string) Settings::get('event_date'));
if ($eventDate !== '' && ($eventTs = strtotime($eventDate)) !== false) {
    $diff = (int) floor(($eventTs - time()) / 86400);
    if ($diff >= 0 && $diff <= 400) {
        $daysLeft = $diff;
    }
}

// El cartell es pot amagar quan ja hi ha fotografia de fons.
$showPoster = $poster !== '' && (Settings::bool('hero_show_poster', true) || $heroImage === '');
?>

<section class="hero <?= $heroImage !== '' ? 'hero--image' : '' ?>" <?= $heroStyle ?>>
    <div class="wrap hero__grid">
        <div>
            <span class="hero__eyebrow">Inscripcions obertes<?= $salesOpen && $anyOnSale ? '' : ' · properament' ?></span>
            <h1><?= e(Settings::get('event_name')) ?></h1>
            <p class="hero__tagline"><?= e(Settings::get('event_tagline')) ?></p>
            <p class="hero__lead"><?= nl2br(e(Settings::get('event_description'))) ?></p>

            <div class="hero__meta">
                <?php if ($daysLeft !== null): ?>
                    <span class="hero__countdown">
                        <?php if ($daysLeft === 0): ?>
                            🎉 <strong>És avui!</strong>
                        <?php elseif ($daysLeft === 1): ?>
                            ⏳ <strong>Demà!</strong>
                        <?php else: ?>
                            ⏳ Falten <strong><?= $daysLeft ?></strong> dies
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <span>📅 <?= e(Settings::get('event_date_text')) ?></span>
                <?php if (($place = trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) !== ''): ?>
                    <span>📍 <?= e($place) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($salesOpen && $anyOnSale): ?>
                <a class="btn btn--accent btn--lg" href="#inscripcions">Vull inscriure'm →</a>
            <?php endif; ?>
        </div>

        <div>
            <?php if ($showPoster): ?>
                <img class="hero__poster" src="<?= e(url($poster)) ?>" alt="Cartell de <?= e(Settings::get('event_name')) ?>">
            <?php elseif ($highlights !== []): ?>
                <div class="hero__highlights">
                    <?php foreach ($highlights as $index => $highlight): ?>
                        <div class="hero__highlight">
                            <i aria-hidden="true"><?= ['🥘', '🎵', '🎱', '🎉', '⭐'][$index % 5] ?></i>
                            <span><?= e($highlight) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($showPoster && $highlights !== []): ?>
    <section class="section section--tight" id="informacio">
        <div class="wrap">
            <div class="ticket-grid">
                <?php foreach ($highlights as $index => $highlight): ?>
                    <div class="card">
                        <div class="card__body" style="display:flex;align-items:center;gap:14px;">
                            <span style="font-size:1.9rem;line-height:1;" aria-hidden="true"><?= ['🥘', '🎵', '🎱', '🎉', '⭐'][$index % 5] ?></span>
                            <strong><?= e($highlight) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section" id="inscripcions">
    <div class="wrap">
        <div class="section__head">
            <h2>Tria la teva inscripció</h2>
            <p>Selecciona el nombre de places de cada tipus. El pagament es fa amb targeta de crèdit
               a través de Stripe, de manera totalment segura.</p>
        </div>

        <?php if (!$salesOpen): ?>
            <div class="alert alert--warning">
                <span aria-hidden="true">⚠️</span>
                <span><?= e(Settings::get('sales_closed_message')) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($types === []): ?>
            <div class="card"><div class="card__body">
                <p style="margin:0;">Encara no hi ha cap tipus d'inscripció publicat. Torna a consultar-ho més endavant.</p>
            </div></div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/inscripcio')) ?>" data-cart-form data-max-total="<?= $maxTotal ?>" data-guard>
                <?= Csrf::field() ?>

                <div class="ticket-grid">
                    <?php foreach ($types as $type):
                        $available = $salesOpen && $type['on_sale'];
                        $remaining = $type['remaining'];
                        $max = $available
                            ? min((int) $type['max_per_order'] ?: 10, $remaining ?? 99, $maxTotal ?: 99)
                            : 0;
                        $includes = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $type['includes']) ?: [])));
                    ?>
                        <article class="ticket-card <?= $available ? '' : 'is-unavailable' ?>">
                            <div class="ticket-card__top">
                                <h3 class="ticket-card__name"><?= e($type['name']) ?></h3>
                                <div class="ticket-card__price">
                                    <?= (int) $type['price_cents'] === 0 ? 'Gratuïta' : money((int) $type['price_cents']) ?>
                                    <small>per persona</small>
                                </div>
                            </div>

                            <?php if (trim((string) $type['description']) !== ''): ?>
                                <p class="ticket-card__desc"><?= nl2br(e($type['description'])) ?></p>
                            <?php endif; ?>

                            <?php if ($includes !== []): ?>
                                <ul class="includes">
                                    <?php foreach ($includes as $item): ?>
                                        <li><span><?= e($item) ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="ticket-card__foot">
                                <?php if (!$available): ?>
                                    <span class="stock stock--out"><?= e($type['sale_note'] ?: 'No disponible') ?></span>
                                <?php else: ?>
                                    <span class="stock <?= $remaining !== null && $remaining <= 10 ? 'stock--low' : '' ?>">
                                        <?= $remaining === null ? 'Places disponibles' : ('Queden ' . (int) $remaining . ' places') ?>
                                    </span>
                                    <div class="qty">
                                        <button type="button" data-qty="-1" aria-label="Treure una inscripció de <?= e($type['name']) ?>">−</button>
                                        <input class="qty-input" type="number" inputmode="numeric"
                                               name="qty[<?= (int) $type['id'] ?>]" value="0"
                                               min="0" max="<?= $max ?>"
                                               data-price="<?= (int) $type['price_cents'] ?>"
                                               aria-label="Nombre d'inscripcions de <?= e($type['name']) ?>">
                                        <button type="button" data-qty="1" aria-label="Afegir una inscripció de <?= e($type['name']) ?>">+</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($salesOpen && $anyOnSale): ?>
                    <div class="cart-bar">
                        <div class="cart-bar__total">
                            <strong data-cart-total>0,00 €</strong>
                            <span class="cart-bar__count" data-cart-count>Encara no has triat cap inscripció</span>
                        </div>
                        <button type="submit" class="btn btn--accent btn--lg" data-cart-submit disabled
                                data-loading="Preparant…">
                            Continuar →
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="section section--tight">
    <div class="wrap">
        <div class="card"><div class="card__body">
            <div class="field-row" style="align-items:center;">
                <div>
                    <h3 style="margin-bottom:.3em;">Ja t'has inscrit?</h3>
                    <p style="margin:0;color:var(--pdsh-muted);">
                        Introdueix el teu correu electrònic i t'enviarem un enllaç per veure,
                        descarregar o anul·lar les teves entrades.
                    </p>
                </div>
                <div style="text-align:right;">
                    <a class="btn btn--ghost" href="<?= e(url('/les-meves-entrades')) ?>">Les meves entrades</a>
                </div>
            </div>
        </div></div>
    </div>
</section>
