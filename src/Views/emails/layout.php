<?php
/** Plantilla HTML dels correus transaccionals i dels comunicats. */
use App\Core\Settings;
use App\Core\Url;

$primary = (string) Settings::get('brand_primary');
$accent  = (string) Settings::get('brand_accent');
$cream   = (string) Settings::get('brand_cream');
$ink     = (string) Settings::get('brand_ink');
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? Settings::get('event_name')) ?></title>
</head>
<body style="margin:0;padding:0;background:<?= e($cream) ?>;font-family:'Segoe UI',system-ui,-apple-system,Helvetica,Arial,sans-serif;color:<?= e($ink) ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= e($cream) ?>;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06);">

                <tr>
                    <td style="background:<?= e($primary) ?>;padding:26px 30px;border-bottom:4px solid <?= e($accent) ?>;">
                        <div style="color:<?= e($cream) ?>;font-size:19px;font-weight:800;line-height:1.3;">
                            <?= e(Settings::get('event_name')) ?>
                        </div>
                        <div style="color:<?= e($accent) ?>;font-size:14px;font-weight:600;margin-top:4px;">
                            <?= e(Settings::get('event_date_text')) ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px;font-size:15px;line-height:1.65;">
                        <?php if (!empty($title)): ?>
                            <h1 style="margin:0 0 18px;font-size:21px;color:<?= e($primary) ?>;"><?= e($title) ?></h1>
                        <?php endif; ?>

                        <?= $bodyHtml ?? '' ?>

                        <?php foreach (($buttons ?? []) as $button): ?>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
                                <tr>
                                    <td style="background:<?= e((string) Settings::get('brand_secondary')) ?>;border-radius:999px;">
                                        <a href="<?= e($button['url']) ?>"
                                           style="display:inline-block;padding:13px 28px;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;">
                                            <?= e($button['label']) ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        <?php endforeach; ?>
                    </td>
                </tr>

                <tr>
                    <td style="background:<?= e($cream) ?>;padding:20px 30px;font-size:12px;line-height:1.6;color:#7A7268;">
                        <?= nl2br(e(Settings::get('mail_footer'))) ?><br><br>
                        <strong><?= e(Settings::get('event_organizer')) ?></strong><br>
                        <?= e(trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))) ?><br>
                        <?php if (($contact = (string) Settings::get('event_contact_email')) !== ''): ?>
                            <a href="mailto:<?= e($contact) ?>" style="color:<?= e($primary) ?>;"><?= e($contact) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>

            </table>
            <div style="max-width:600px;margin:14px auto 0;font-size:11px;color:#9A9188;text-align:center;">
                <?= e(Url::host()) ?>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
