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
        <div class="panel__head"><div><h2>Imatges</h2><p>El cartell es mostra a la portada; el logotip, a la capçalera.</p></div></div>
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
