<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Core\Settings;

/**
 * Cua d'enviaments. Els correus massius no s'envien durant la petició web:
 * s'encuen i es processen per lots (cron o botó "Enviar ara" del panell),
 * així no es bloqueja el navegador ni es dispara el límit del servidor SMTP.
 */
final class MailQueue
{
    public static function push(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $body,
        ?int $campaignId = null,
        ?string $attachmentPath = null
    ): int {
        return Db::insert('email_queue', [
            'campaign_id'     => $campaignId,
            'to_email'        => $toEmail,
            'to_name'         => $toName,
            'subject'         => $subject,
            'body'            => $body,
            'attachment_path' => $attachmentPath,
            'status'          => 'pending',
        ]);
    }

    /**
     * Processa fins a $limit correus pendents.
     *
     * @return array{sent:int, failed:int, remaining:int}
     */
    public static function process(?int $limit = null): array
    {
        $limit = $limit ?? max(1, Settings::int('smtp_batch_size', 25));
        $mailer = new Mailer();
        $sent = 0;
        $failed = 0;

        $pending = Db::all(
            'SELECT * FROM `email_queue`
             WHERE `status` = :s AND `available_at` <= NOW() AND `attempts` < 3
             ORDER BY `id` ASC LIMIT ' . (int) $limit,
            ['s' => 'pending']
        );

        foreach ($pending as $item) {
            // Marquem l'element abans d'enviar per evitar enviaments duplicats
            // si dues execucions del cron se solapen.
            $claimed = Db::run(
                'UPDATE `email_queue` SET `status` = :sending, `attempts` = `attempts` + 1
                 WHERE `id` = :id AND `status` = :pending',
                ['sending' => 'sending', 'id' => $item['id'], 'pending' => 'pending']
            )->rowCount();

            if ($claimed === 0) {
                continue;
            }

            $attachments = [];
            if (!empty($item['attachment_path']) && is_file($item['attachment_path'])) {
                $attachments[] = ['path' => $item['attachment_path'], 'name' => basename($item['attachment_path'])];
            }

            $ok = $mailer->send(
                (string) $item['to_email'],
                $item['to_name'] !== null ? (string) $item['to_name'] : null,
                (string) $item['subject'],
                (string) $item['body'],
                $attachments
            );

            if ($ok) {
                $sent++;
                Db::update('email_queue', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error' => null], '`id` = :id', ['id' => $item['id']]);
                if ($item['campaign_id']) {
                    Db::run('UPDATE `campaigns` SET `sent_count` = `sent_count` + 1 WHERE `id` = :id', ['id' => $item['campaign_id']]);
                }
            } else {
                $failed++;
                $attempts = (int) $item['attempts'] + 1;
                Db::update('email_queue', [
                    'status'       => $attempts >= 3 ? 'failed' : 'pending',
                    'error'        => $mailer->lastError(),
                    // Reintent amb espera creixent.
                    'available_at' => date('Y-m-d H:i:s', time() + 300 * $attempts),
                ], '`id` = :id', ['id' => $item['id']]);

                if ($attempts >= 3 && $item['campaign_id']) {
                    Db::run('UPDATE `campaigns` SET `failed_count` = `failed_count` + 1 WHERE `id` = :id', ['id' => $item['campaign_id']]);
                }
            }

            // Petita pausa per no saturar el servidor SMTP.
            usleep(150000);
        }

        self::closeFinishedCampaigns();

        $remaining = (int) Db::value(
            'SELECT COUNT(*) FROM `email_queue` WHERE `status` = :s AND `attempts` < 3',
            ['s' => 'pending'],
            0
        );

        if ($sent > 0 || $failed > 0) {
            Logger::info('Cua de correu processada', ['enviats' => $sent, 'fallits' => $failed, 'pendents' => $remaining]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
    }

    private static function closeFinishedCampaigns(): void
    {
        $running = Db::all("SELECT `id` FROM `campaigns` WHERE `status` IN ('queued','sending')");
        foreach ($running as $campaign) {
            $pending = (int) Db::value(
                "SELECT COUNT(*) FROM `email_queue` WHERE `campaign_id` = :id AND `status` IN ('pending','sending')",
                ['id' => $campaign['id']],
                0
            );
            if ($pending === 0) {
                Db::update('campaigns', ['status' => 'sent', 'finished_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $campaign['id']]);
            } else {
                Db::update('campaigns', ['status' => 'sending'], '`id` = :id AND `status` = \'queued\'', ['id' => $campaign['id']]);
            }
        }
    }

    public static function pendingCount(): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM `email_queue` WHERE `status` = 'pending' AND `attempts` < 3", [], 0);
    }
}
