<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Money;
use App\Core\Settings;

/**
 * Llistat d'inscripcions en PDF (A4 horitzontal) per al Panell de Gestió.
 * Respecta els filtres actius i afegeix un resum amb els totals.
 */
final class RegistrationListPdf extends Pdf
{
    private const PAGE_W = 297.0;
    private const MARGIN = 10.0;

    /** @var array<int, array{label:string, width:float, align:string, key:string}> */
    private array $columns = [];
    private array $filterSummary = [];
    private string $generatedBy = '';

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4');
        $this->SetAutoPageBreak(true, 20);
        $this->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $this->AliasNbPages();

        // Les amplades han de sumar 277 mm: 297 mm d'A4 horitzontal menys els marges.
        $this->columns = [
            ['label' => 'Data',        'width' => 26,  'align' => 'L', 'key' => 'created_at'],
            ['label' => 'Referència',  'width' => 27,  'align' => 'L', 'key' => 'reference'],
            ['label' => 'Codi',        'width' => 24,  'align' => 'L', 'key' => 'code'],
            ['label' => 'Assistent',   'width' => 46,  'align' => 'L', 'key' => 'attendee'],
            ['label' => 'Correu',      'width' => 53,  'align' => 'L', 'key' => 'email'],
            ['label' => 'Telèfon',     'width' => 22,  'align' => 'L', 'key' => 'phone'],
            ['label' => 'Tipus',       'width' => 40,  'align' => 'L', 'key' => 'type_name'],
            ['label' => 'Estat',       'width' => 21,  'align' => 'L', 'key' => 'status_label'],
            ['label' => 'Import',      'width' => 18,  'align' => 'R', 'key' => 'amount'],
        ];
    }

    /**
     * @param array<int, array> $rows    Inscripcions ja formatades.
     * @param array<string,string> $filters Resum llegible dels filtres aplicats.
     * @param array<string,mixed> $totals Totals a mostrar al peu del resum.
     */
    public function build(array $rows, array $filters = [], array $totals = [], string $generatedBy = ''): string
    {
        $this->filterSummary = $filters;
        $this->generatedBy = $generatedBy;

        $this->AddPage();
        $this->summaryBox($totals, count($rows));
        $this->tableHeader();

        $fill = false;
        $ink = (string) Settings::get('brand_ink');
        foreach ($rows as $row) {
            if ($this->GetY() > 185) {
                $this->AddPage();
                $this->tableHeader();
                $fill = false;
            }
            $this->fillHex($fill ? '#F7F4EE' : '#FFFFFF');
            $this->textHex($ink);
            $this->SetFont('Helvetica', '', 7.6);

            $x = self::MARGIN;
            $y = $this->GetY();
            foreach ($this->columns as $col) {
                $this->SetXY($x, $y);
                $this->Cell($col['width'], 6, '', 0, 0, 'L', true);
                $this->SetXY($x + 1, $y);
                $this->fitCell($col['width'] - 2, 6, (string) ($row[$col['key']] ?? ''), 7.6, 5.2, $col['align']);
                $x += $col['width'];
            }
            $this->SetXY(self::MARGIN, $y + 6);
            $fill = !$fill;
        }

        if ($rows === []) {
            $this->SetFont('Helvetica', 'I', 9);
            $this->textHex('#7A7268');
            $this->Cell(0, 12, $this->t('No hi ha cap inscripció que coincideixi amb els filtres aplicats.'), 0, 1, 'C');
        }

        return $this->Output('S');
    }

    private function summaryBox(array $totals, int $count): void
    {
        $primary = (string) Settings::get('brand_primary');
        $cream   = (string) Settings::get('brand_cream');

        $this->fillHex($cream);
        $this->roundedRect(self::MARGIN, $this->GetY(), self::PAGE_W - self::MARGIN * 2, 20, 2, 'F');

        $y = $this->GetY();
        $this->textHex($primary);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetXY(self::MARGIN + 4, $y + 3);
        $this->Cell(120, 5, $this->t('Resum'), 0, 0, 'L');

        $this->textHex((string) Settings::get('brand_ink'));
        $this->SetFont('Helvetica', '', 8);

        $summary = [];
        $summary[] = 'Inscripcions llistades: ' . $count;
        foreach ($totals as $label => $value) {
            $summary[] = $label . ': ' . $value;
        }
        $this->SetXY(self::MARGIN + 4, $y + 8.5);
        $this->MultiCell(160, 4.2, $this->t(implode('   ·   ', $summary)), 0, 'L');

        if ($this->filterSummary !== []) {
            $filters = [];
            foreach ($this->filterSummary as $label => $value) {
                $filters[] = $label . ': ' . $value;
            }
            $this->textHex('#7A7268');
            $this->SetFont('Helvetica', '', 7.5);
            $this->SetXY(self::PAGE_W - self::MARGIN - 116, $y + 3);
            $this->MultiCell(112, 4, $this->t('Filtres aplicats — ' . implode('  ·  ', $filters)), 0, 'R');
        }

        $this->SetXY(self::MARGIN, $y + 24);
    }

    private function tableHeader(): void
    {
        $this->fillHex((string) Settings::get('brand_primary'));
        $this->textHex((string) Settings::get('brand_cream'));
        $this->SetFont('Helvetica', 'B', 7.8);

        $x = self::MARGIN;
        $y = $this->GetY();
        foreach ($this->columns as $col) {
            $this->SetXY($x, $y);
            $this->Cell($col['width'], 7, '', 0, 0, 'L', true);
            $this->SetXY($x + 1, $y);
            $this->Cell($col['width'] - 2, 7, $this->t($col['label']), 0, 0, $col['align']);
            $x += $col['width'];
        }
        $this->SetXY(self::MARGIN, $y + 7);
    }

    public function Header(): void
    {
        if ($this->PageNo() > 1) {
            $this->SetY(self::MARGIN);
            return;
        }
        $this->textHex((string) Settings::get('brand_primary'));
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 8, $this->t('Llistat d\'inscripcions'), 0, 1, 'L');

        $this->textHex('#7A7268');
        $this->SetFont('Helvetica', '', 9);
        $this->Cell(0, 5, $this->t((string) Settings::get('event_name') . '  ·  ' . Settings::get('event_date_text')), 0, 1, 'L');
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->drawHex('#E2DACE');
        $this->SetLineWidth(0.3);
        $this->Line(self::MARGIN, $this->GetY(), self::PAGE_W - self::MARGIN, $this->GetY());

        $this->SetY(-11);
        $this->SetFont('Helvetica', '', 7.5);
        $this->textHex('#7A7268');
        $left = 'Generat el ' . date('d/m/Y H:i') . ($this->generatedBy !== '' ? ' per ' . $this->generatedBy : '');
        $this->Cell(140, 5, $this->t($left), 0, 0, 'L');
        $this->Cell(0, 5, $this->t('Pàgina ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /** Format uniforme d'un import per a les cel·les del llistat. */
    public static function amount(int $cents): string
    {
        return Money::format($cents);
    }
}
