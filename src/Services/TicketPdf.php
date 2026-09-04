<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Money;
use App\Core\Settings;
use App\Core\Url;

/**
 * Entrada / inscripció en PDF: una pàgina A4 per entrada, pensada tant per
 * imprimir com per llegir-la còmodament des del mòbil (tipografia gran i
 * un únic bloc central amb el codi QR).
 */
final class TicketPdf
{
    private const MARGIN = 14.0;
    private const PAGE_W = 210.0;

    /** @var string[] Fitxers temporals de QR a esborrar en acabar. */
    private array $tempFiles = [];

    /**
     * @param array                     $order    Fila de `orders`.
     * @param array<int, array>         $tickets  Files de `tickets` a incloure.
     * @param array<int, array>         $types    Tipus d'entrada indexats per id.
     */
    public function render(array $order, array $tickets, array $types): string
    {
        $pdf = new Pdf('P', 'mm', 'A4');
        $pdf->SetTitle('Entrada ' . ($order['reference'] ?? ''), true);
        $pdf->SetAuthor((string) Settings::get('event_organizer'), true);
        $pdf->SetCreator('poudeshorta.cat', true);
        $pdf->SetSubject((string) Settings::get('event_name'), true);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);

        $total = count($tickets);
        $index = 0;
        foreach ($tickets as $ticket) {
            $index++;
            $type = $types[(int) $ticket['ticket_type_id']] ?? [];
            $this->page($pdf, $order, $ticket, $type, $index, $total);
        }

        $output = $pdf->Output('S');
        $this->cleanup();

        return $output;
    }

    private function page(Pdf $pdf, array $order, array $ticket, array $type, int $index, int $total): void
    {
        $primary   = (string) Settings::get('brand_primary');
        $secondary = (string) Settings::get('brand_secondary');
        $accent    = (string) Settings::get('brand_accent');
        $cream     = (string) Settings::get('brand_cream');
        $ink       = (string) Settings::get('brand_ink');

        $pdf->AddPage();
        $contentW = self::PAGE_W - self::MARGIN * 2;

        // ---------- Capçalera de color ----------
        $pdf->fillHex($primary);
        $pdf->Rect(0, 0, self::PAGE_W, 40, 'F');
        $pdf->fillHex($accent);
        $pdf->Rect(0, 40, self::PAGE_W, 2.5, 'F');

        $pdf->textHex($cream);
        $pdf->SetFont('Helvetica', 'B', 19);
        $pdf->SetXY(self::MARGIN, 10);
        $pdf->fitCell($contentW - 40, 9, (string) Settings::get('event_name'), 19, 12);

        $pdf->SetFont('Helvetica', '', 11);
        $pdf->SetXY(self::MARGIN, 21);
        $pdf->fitCell($contentW - 40, 6, (string) Settings::get('event_tagline'), 11, 8);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->textHex($accent);
        $pdf->SetXY(self::MARGIN, 29.5);
        $pdf->Cell($contentW - 40, 5, $pdf->t('ENTRADA · INSCRIPCIÓ'), 0, 0, 'L');

        // Numeració de l'entrada dins la comanda.
        if ($total > 1) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->textHex($cream);
            $pdf->SetXY(self::PAGE_W - self::MARGIN - 40, 29.5);
            $pdf->Cell(40, 5, $pdf->t($index . ' de ' . $total), 0, 0, 'R');
        }

        // ---------- Targeta principal ----------
        $cardY = 52.0;
        $cardH = 118.0;
        $pdf->drawHex('#DDD5C8');
        $pdf->fillHex('#FFFFFF');
        $pdf->SetLineWidth(0.4);
        $pdf->roundedRect(self::MARGIN, $cardY, $contentW, $cardH, 4, 'DF');

        // Columna esquerra: dades de l'entrada.
        $leftX = self::MARGIN + 10;
        $leftW = 96.0;
        $y = $cardY + 11;

        $pdf->textHex($secondary);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetXY($leftX, $y);
        $pdf->Cell($leftW, 5, $pdf->t('TIPUS D\'INSCRIPCIÓ'), 0, 0, 'L');
        $y += 6;

        $pdf->textHex($ink);
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->SetXY($leftX, $y);
        $pdf->fitCell($leftW, 8, (string) ($type['name'] ?? 'Inscripció'), 17, 11);
        $y += 13;

        $attendee = trim((string) ($ticket['attendee_name'] ?? ''));
        if ($attendee === '') {
            $attendee = trim(($order['name'] ?? '') . ' ' . ($order['surname'] ?? ''));
        }

        $rows = [
            ['Assistent', $attendee !== '' ? $attendee : '—'],
            ['Data', (string) Settings::get('event_date_text')],
            ['Lloc', trim((string) Settings::get('event_location') . ' ' . (string) Settings::get('event_city'))],
            ['Referència', (string) ($order['reference'] ?? '')],
            ['Import', Money::format((int) ($ticket['price_cents'] ?? 0))],
        ];

        foreach ($rows as [$label, $value]) {
            $pdf->textHex('#7A7268');
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetXY($leftX, $y);
            $pdf->Cell($leftW, 4.5, $pdf->t(mb_strtoupper($label)), 0, 0, 'L');

            $pdf->textHex($ink);
            $pdf->SetFont('Helvetica', 'B', 11.5);
            $pdf->SetXY($leftX, $y + 4.5);
            $pdf->fitCell($leftW, 6, $value !== '' ? $value : '—', 11.5, 8);
            $y += 12.5;
        }

        // Camps addicionals del formulari, si n'hi ha.
        $extra = json_decode((string) ($ticket['extra_json'] ?? ''), true);
        if (is_array($extra) && $extra !== []) {
            $pieces = [];
            foreach ($extra as $label => $value) {
                if ($value !== '' && $value !== null) {
                    $pieces[] = $label . ': ' . (is_array($value) ? implode(', ', $value) : $value);
                }
            }
            if ($pieces !== []) {
                $pdf->textHex('#7A7268');
                $pdf->SetFont('Helvetica', '', 8.5);
                $pdf->SetXY($leftX, $y);
                $pdf->MultiCell($leftW, 4.2, $pdf->t(implode('  ·  ', $pieces)), 0, 'L');
            }
        }

        // Separador vertical de punts (com el tall d'una entrada).
        $sepX = self::MARGIN + $leftW + 16;
        $pdf->drawHex('#D8CFC2');
        $pdf->SetLineWidth(0.3);
        $pdf->dashedLine($sepX, $cardY + 10, $sepX, $cardY + $cardH - 10);

        // Columna dreta: codi QR.
        $qrSize = 52.0;
        $qrX = $sepX + ((self::MARGIN + $contentW) - $sepX - $qrSize) / 2;
        $qrY = $cardY + 14;

        $qrPath = QrCode::toTempFile(Url::full('/e/' . ($ticket['code'] ?? '')), 620);
        $this->tempFiles[] = $qrPath;
        $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize, 'PNG');

        $pdf->textHex($ink);
        $pdf->SetFont('Courier', 'B', 13);
        $pdf->SetXY($sepX, $qrY + $qrSize + 4);
        $pdf->Cell((self::MARGIN + $contentW) - $sepX, 6, $pdf->t((string) ($ticket['code'] ?? '')), 0, 0, 'C');

        $pdf->textHex('#7A7268');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($sepX, $qrY + $qrSize + 11);
        $pdf->MultiCell((self::MARGIN + $contentW) - $sepX, 4, $pdf->t("Mostreu aquest codi\na l'entrada"), 0, 'C');

        // ---------- Què inclou ----------
        $boxY = $cardY + $cardH + 8;
        $boxH = 0.0;
        $includes = trim((string) ($type['includes'] ?? ''));
        if ($includes !== '') {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $includes) ?: [])));
            $boxH = 14.0 + count($lines) * 5.4;

            $pdf->fillHex($cream);
            $pdf->roundedRect(self::MARGIN, $boxY, $contentW, $boxH, 3, 'F');

            $pdf->textHex($primary);
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $pdf->SetXY(self::MARGIN + 8, $boxY + 5);
            $pdf->Cell($contentW - 16, 5, $pdf->t('QUÈ INCLOU LA TEVA INSCRIPCIÓ'), 0, 0, 'L');

            $pdf->SetFont('Helvetica', '', 10.5);
            $lineY = $boxY + 11.5;
            foreach ($lines as $line) {
                $pdf->fillHex($secondary);
                $pdf->Rect(self::MARGIN + 8.5, $lineY + 2, 2, 2, 'F');
                $pdf->textHex($ink);
                $pdf->SetXY(self::MARGIN + 13, $lineY);
                $pdf->fitCell($contentW - 21, 5.4, $line, 10.5, 8);
                $lineY += 5.4;
            }
        }

        // ---------- Peu ----------
        $footY = max($boxY + $boxH, $cardY + $cardH) + 12;

        $pdf->drawHex('#E2DACE');
        $pdf->SetLineWidth(0.3);
        $pdf->Line(self::MARGIN, $footY, self::PAGE_W - self::MARGIN, $footY);

        $pdf->textHex('#7A7268');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetXY(self::MARGIN, $footY + 4);
        $pdf->MultiCell($contentW, 4.4, $pdf->t(
            "Aquesta entrada és personal i intransferible. Cal presentar-la, impresa o al mòbil, a l'accés de l'esdeveniment.\n"
            . 'Organitza: ' . Settings::get('event_organizer') . '  ·  Consultes: ' . Settings::get('event_contact_email')
        ), 0, 'L');

        // Franja inferior de marca, per equilibrar la pàgina.
        $pdf->fillHex($accent);
        $pdf->Rect(0, 285, self::PAGE_W, 1.8, 'F');
        $pdf->fillHex($primary);
        $pdf->Rect(0, 286.8, self::PAGE_W, 10.2, 'F');

        $pdf->textHex($cream);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY(self::MARGIN, 289.5);
        $pdf->Cell($contentW / 2, 5, $pdf->t('Emesa el ' . date('d/m/Y H:i')), 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetXY(self::MARGIN + $contentW / 2, 289.5);
        $pdf->Cell($contentW / 2, 5, $pdf->t('poudeshorta.cat'), 0, 0, 'R');
    }

    private function cleanup(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
    }
}
