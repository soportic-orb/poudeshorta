<?php
use App\Core\Csrf;
use App\Core\View;

$s = $settings;
$colors = [
    'brand_primary'   => ['Color principal', 'Capçaleres, títols i franja de l\'entrada.'],
    'brand_secondary' => ['Color d\'acció', 'Botons principals i elements destacats.'],
    'brand_accent'    => ['Color d\'accent', 'Etiquetes, línies i detalls daurats.'],
    'brand_cream'     => ['Fons clar', 'Fons general de les pàgines.'],
    'brand_olive'     => ['Verd complementari', 'Estats correctes i detalls.'],
    'brand_ink'       => ['Color del text', 'Text principal i barra lateral.'],
];
?>

<?= View::partial('admin/_settings_tabs') ?>

<form method="post" action="<?= e(url('/admin/configuracio/aparenca')) ?>" enctype="multipart/form-data">
    <?= Csrf::field() ?>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Colors de la plataforma</h2>
                <p>Els valors per defecte estan extrets del cartell de l'esdeveniment.</p>
            </div>
        </div>
        <div class="panel__body">
            <div class="form-grid">
                <?php foreach ($colors as $key => [$label, $hint]): ?>
                    <div class="field">
                        <label for="<?= $key ?>"><?= e($label) ?></label>
                        <div class="color-field">
                            <input type="color" id="<?= $key ?>_picker" value="<?= e($s[$key]) ?>"
                                   aria-label="Selector de color per a <?= e($label) ?>"
                                   oninput="document.getElementById('<?= $key ?>').value = this.value.toUpperCase();">
                            <input class="input" type="text" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($s[$key]) ?>"
                                   pattern="#[0-9A-Fa-f]{6}"
                                   oninput="document.getElementById('<?= $key ?>_picker').value = this.value;">
                        </div>
                        <span class="field__hint"><?= e($hint) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="swatches">
                <?php foreach ($colors as $key => [$label]): ?>
                    <span class="swatch"><i style="background:<?= e($s[$key]) ?>;"></i> <?= e($label) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head">
            <div>
                <h2>Imatge de fons de la capçalera</h2>
                <p>Una fotografia darrere del títol de la portada. Si no en poses cap, es fa servir el degradat de la marca.</p>
            </div>
        </div>
        <div class="panel__body">
            <?php if (($hero = (string) $s['hero_image']) !== ''): ?>
                <img src="<?= e(url($hero)) ?>" alt="Fons actual de la capçalera"
                     style="width:100%;max-width:520px;border-radius:12px;border:1px solid var(--pdsh-line);margin-bottom:12px;">
                <label class="check" style="margin-bottom:16px;">
                    <input type="checkbox" name="remove_hero" value="1">
                    <span>Eliminar la imatge de fons i tornar al degradat</span>
                </label>
            <?php endif; ?>

            <div class="field">
                <label for="hero">Pujar una imatge</label>
                <input class="input" type="file" id="hero" name="hero" accept="image/jpeg,image/png,image/webp">
                <span class="field__hint">
                    Horitzontal i de bona resolució: 1920 × 1080 px o més. JPG, PNG o WebP, màxim 6 MB.
                    Evita les imatges amb text, perquè el títol s'hi superposa.
                </span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="hero_overlay">Intensitat del vel de color</label>
                    <div style="display:flex;gap:12px;align-items:center;">
                        <input type="range" id="hero_overlay_range" min="0" max="90" step="5"
                               value="<?= (int) $s['hero_overlay'] ?>" style="flex:1;accent-color:var(--pdsh-secondary);"
                               oninput="document.getElementById('hero_overlay').value = this.value;">
                        <input class="input" type="number" id="hero_overlay" name="hero_overlay" min="0" max="90"
                               value="<?= (int) $s['hero_overlay'] ?>" style="width:88px;"
                               oninput="document.getElementById('hero_overlay_range').value = this.value;">
                    </div>
                    <span class="field__hint">
                        Com més alt, més tapada queda la fotografia i més es llegeix el text. 0 la deixa neta; 55 és un bon punt de partida.
                    </span>
                </div>

                <div class="field">
                    <label for="hero_focus">Part de la imatge que es veu</label>
                    <select class="select" id="hero_focus" name="hero_focus">
                        <option value="center"<?= selectedIf($s['hero_focus'] === 'center') ?>>Centre</option>
                        <option value="top"<?= selectedIf($s['hero_focus'] === 'top') ?>>Part superior</option>
                        <option value="bottom"<?= selectedIf($s['hero_focus'] === 'bottom') ?>>Part inferior</option>
                    </select>
                    <span class="field__hint">La imatge s'escapça per omplir l'espai; això tria quina part es conserva.</span>
                </div>
            </div>

            <div class="field" style="margin-bottom:0;">
                <label class="check">
                    <input type="checkbox" name="hero_show_poster" value="1"<?= checkedIf($s['hero_show_poster'] === '1') ?>>
                    <span>Continuar mostrant el cartell al costat del títol</span>
                </label>
                <span class="field__hint">
                    Si el desmarques i hi ha imatge de fons, al costat del títol hi apareixeran els atractius de l'esdeveniment.
                </span>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel__head"><div><h2>Cartell i logotip</h2><p>El cartell es mostra al costat del títol; el logotip, a la barra superior.</p></div></div>
        <div class="panel__body">
            <div class="form-grid">
                <div class="field">
                    <label for="poster">Cartell de l'esdeveniment</label>
                    <?php if (($poster = (string) $s['event_poster']) !== ''): ?>
                        <img src="<?= e(url($poster)) ?>" alt="Cartell actual"
                             style="max-width:180px;border-radius:10px;border:1px solid var(--pdsh-line);margin-bottom:10px;">
                        <label class="check" style="margin-bottom:10px;">
                            <input type="checkbox" name="remove_poster" value="1">
                            <span>Eliminar el cartell actual</span>
                        </label>
                    <?php endif; ?>
                    <input class="input" type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp">
                    <span class="field__hint">JPG, PNG o WebP. Màxim 6 MB.</span>
                </div>

                <div class="field">
                    <label for="logo">Logotip</label>
                    <?php if (($logo = (string) $s['event_logo']) !== ''): ?>
                        <img src="<?= e(url($logo)) ?>" alt="Logotip actual"
                             style="max-width:150px;border-radius:10px;border:1px solid var(--pdsh-line);margin-bottom:10px;background:#fff;padding:8px;">
                        <label class="check" style="margin-bottom:10px;">
                            <input type="checkbox" name="remove_logo" value="1">
                            <span>Eliminar el logotip actual</span>
                        </label>
                    <?php endif; ?>
                    <input class="input" type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                    <span class="field__hint">Preferiblement PNG amb fons transparent.</span>
                </div>
            </div>
        </div>
        <div class="panel__foot">
            <button type="submit" class="btn btn--primary">Desar l'aparença</button>
            <a class="btn btn--light" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Veure el web públic ↗</a>
        </div>
    </div>
</form>
