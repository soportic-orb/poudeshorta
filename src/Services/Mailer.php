<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Settings;
use App\Core\Str;
use App\Core\Url;
use App\Core\View;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class Mailer
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function isConfigured(): bool
    {
        return trim((string) Settings::get('smtp_host')) !== ''
            && trim((string) Settings::get('smtp_from_email')) !== '';
    }

    /**
     * @param array<int, array{path?:string, content?:string, name:string, mime?:string}> $attachments
     */
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $htmlBody,
        array $attachments = [],
        ?string $plainBody = null
    ): bool {
        $this->lastError = null;

        try {
            $mail = $this->newMailer();
            $mail->addAddress($toEmail, $toName ?? '');
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody ?? $this->toPlainText($htmlBody);

            foreach ($attachments as $attachment) {
                if (!empty($attachment['path']) && is_file($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'], 'base64', $attachment['mime'] ?? 'application/pdf');
                } elseif (isset($attachment['content'])) {
                    $mail->addStringAttachment($attachment['content'], $attachment['name'], 'base64', $attachment['mime'] ?? 'application/pdf');
                }
            }

            $mail->send();
            return true;
        } catch (MailException $e) {
            $this->lastError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Logger::exception($e, 'Mailer');
        }

        Logger::error('No s\'ha pogut enviar el correu', ['to' => $toEmail, 'error' => $this->lastError]);
        return false;
    }

    /** Envia un correu de prova a l'adreça indicada i retorna [èxit, missatge]. */
    public function sendTest(string $toEmail): array
    {
        $html = $this->wrap(
            'Prova de configuració SMTP',
            '<p>Aquest és un correu de prova enviat des del Panell de Gestió de <strong>' . htmlspecialchars((string) Settings::get('event_name')) . '</strong>.</p>'
            . '<p>Si el llegeixes, la configuració del servidor SMTP és correcta.</p>'
        );
        $ok = $this->send($toEmail, null, 'Prova SMTP · ' . Settings::get('event_name'), $html);
        return [$ok, $ok ? 'Correu de prova enviat correctament.' : ('Error: ' . ($this->lastError ?? 'desconegut'))];
    }

    private function newMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->XMailer = ' ';
        $mail->Timeout = 20;

        $host = trim((string) Settings::get('smtp_host'));
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = Settings::int('smtp_port', 587);
            $mail->SMTPAuth = Settings::bool('smtp_auth', true);
            if ($mail->SMTPAuth) {
                $mail->Username = (string) Settings::get('smtp_user');
                $mail->Password = (string) Settings::get('smtp_pass');
            }
            $secure = (string) Settings::get('smtp_secure', 'tls');
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        } else {
            $mail->isMail();
        }

        $fromEmail = (string) Settings::get('smtp_from_email');
        $mail->setFrom($fromEmail !== '' ? $fromEmail : 'no-reply@' . Url::host(), (string) Settings::get('smtp_from_name'));

        $replyTo = trim((string) Settings::get('smtp_reply_to'));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        return $mail;
    }

    /** Embolcalla un cos HTML amb la plantilla de marca de la plataforma. */
    public function wrap(string $title, string $bodyHtml, array $buttons = []): string
    {
        return View::partial('emails/layout', [
            'title'    => $title,
            'bodyHtml' => $bodyHtml,
            'buttons'  => $buttons,
        ]);
    }

    /** Converteix un cos escrit en text pla a HTML senzill (per a les campanyes). */
    /** Converteix un cos escrit en text pla a HTML senzill (per a les campanyes). */
    public static function textToHtml(string $text): string
    {
        // Els gestors de correu no apliquen fulls d'estil: l'enllaç ha de
        // portar el color a dins.
        return Str::toHtmlParagraphs($text, 'style="color:#8C1027;"');
    }

    private function toPlainText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|h[1-6]|tr)>#i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
