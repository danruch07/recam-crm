<?php
declare(strict_types=1);

namespace Recam\Pdf;

use FPDF;

final class ReportPdf
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;
    private const MARGIN = 40.0;
    private const HEADER_H = 74.0;
    private const FOOTER_H = 36.0;

    private FPDF $pdf;
    private float $y;

    public function __construct(
        private string $company,
        private string $docTitle,
        private string $folio,
        private string $issuedAt,
        private string $generatedBy,
        private ?string $headerNote = null
    ) {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        $this->pdf = new FPDF('P', 'pt', 'A4');
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->AliasNbPages();
        $this->pdf->SetTitle($this->docTitle, true);
        $this->pdf->SetAuthor($this->generatedBy, true);
        $this->pdf->SetCreator('RECAM LASER CRM', true);
        $this->addPage();
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(34);
        $this->y += 10;
        $this->text(self::MARGIN, $this->y + 12, $text, 12, true, [0.12, 0.14, 0.18]);
        $this->y += 18;
        $this->line(self::MARGIN, $this->y, self::PAGE_W - self::MARGIN, $this->y, [0.84, 0.16, 0.16], 1.2);
        $this->y += 8;
    }

    public function keyValues(array $pairs, float $labelWidth = 95): void
    {
        $valueWidth = self::PAGE_W - 2 * self::MARGIN - $labelWidth;
        foreach ($pairs as $label => $value) {
            $lines = $this->wrap($value === '' ? '—' : $value, $valueWidth, 10, false);
            $this->ensureSpace(14 * count($lines));
            $this->text(self::MARGIN, $this->y + 10, $label, 9.5, true, [0.4, 0.44, 0.5]);
            foreach ($lines as $i => $line) {
                $this->text(self::MARGIN + $labelWidth, $this->y + 10 + $i * 13, $line, 10, false);
            }
            $this->y += 13 * count($lines) + 3;
        }
    }

    public function paragraph(string $text, float $size = 10, bool $bold = false): void
    {
        foreach ($this->wrap($text, self::PAGE_W - 2 * self::MARGIN, $size, $bold) as $line) {
            $this->ensureSpace($size + 4);
            $this->text(self::MARGIN, $this->y + $size, $line, $size, $bold);
            $this->y += $size + 3;
        }
    }

    public function spacer(float $h = 8): void
    {
        $this->y += $h;
    }

    public function table(array $headers, array $widths, array $rows): void
    {
        $size = 9;
        $pad  = 4;
        $lh   = 11.5;

        $drawHeader = function () use ($headers, $widths, $size, $pad): void {
            $this->ensureSpace(22);
            $x = self::MARGIN;
            $this->rect($x, $this->y, array_sum($widths), 18, [0.13, 0.15, 0.19]);
            foreach ($headers as $i => $h) {
                $this->text($x + $pad, $this->y + 12.5, $h, $size, true, [1, 1, 1]);
                $x += $widths[$i];
            }
            $this->y += 18;
        };
        $drawHeader();

        foreach ($rows as $n => $row) {
            $cells = [];
            $maxLines = 1;
            foreach ($row as $i => $cell) {
                $cells[$i] = $this->wrap($cell === '' ? '—' : $cell, $widths[$i] - 2 * $pad, $size, false);
                $maxLines = max($maxLines, count($cells[$i]));
            }
            $rowH = $maxLines * $lh + 2 * $pad - 2;

            if ($this->y + $rowH > self::PAGE_H - self::FOOTER_H - 10) {
                $this->addPage();
                $drawHeader();
            }

            if ($n % 2 === 1) {
                $this->rect(self::MARGIN, $this->y, array_sum($widths), $rowH, [0.955, 0.96, 0.97]);
            }
            $x = self::MARGIN;
            foreach ($cells as $i => $lines) {
                foreach ($lines as $k => $line) {
                    $this->text($x + $pad, $this->y + $pad + 8 + $k * $lh, $line, $size, false);
                }
                $x += $widths[$i];
            }
            $this->y += $rowH;
            $this->line(self::MARGIN, $this->y, self::MARGIN + array_sum($widths), $this->y, [0.88, 0.89, 0.91], 0.5);
        }
    }

    public static function usableWidth(): float
    {
        return self::PAGE_W - 2 * self::MARGIN;
    }

    public function output(): string
    {
        return $this->pdf->Output('S');
    }

    private function addPage(): void
    {
        $this->pdf->AddPage();
        $this->y = self::HEADER_H + 14;
        $this->drawHeader();
        $this->drawFooter();
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y + $height > self::PAGE_H - self::FOOTER_H - 10) {
            $this->addPage();
        }
    }

    private function drawHeader(): void
    {
        $this->rect(0, 0, self::PAGE_W, self::HEADER_H, [0.12, 0.145, 0.18]);
        $this->rect(0, self::HEADER_H, self::PAGE_W, 3, [0.84, 0.16, 0.16]);
        $this->text(self::MARGIN, 33, $this->company, 20, true, [1, 1, 1]);
        if ($this->headerNote) {
            $this->text(self::MARGIN, 52, $this->headerNote, 9, false, [0.78, 0.8, 0.84]);
        }
        $this->text(self::MARGIN, 66, 'Fecha de emisión: ' . $this->issuedAt, 9, false, [1, 1, 1]);

        $right = self::PAGE_W - self::MARGIN;
        $this->text($right - $this->width($this->docTitle, 13, true), 33, $this->docTitle, 13, true, [1, 1, 1]);
        $folio = 'Folio: ' . $this->folio;
        $this->text($right - $this->width($folio, 9, false), 52, $folio, 9, false, [0.78, 0.8, 0.84]);
    }

    private function drawFooter(): void
    {
        $y = self::PAGE_H - 22;
        $right = self::PAGE_W - self::MARGIN;
        $this->line(self::MARGIN, $y - 12, $right, $y - 12, [0.8, 0.82, 0.85], 0.5);
        $this->text(self::MARGIN, $y, 'Generado por: ' . $this->generatedBy . ' · Documento de uso interno', 8, false, [0.4, 0.44, 0.5]);
        $label = 'Página ' . $this->pdf->PageNo() . ' de {nb}';
        $this->text($right - $this->width($label, 8, false), $y, $label, 8, false, [0.4, 0.44, 0.5]);
    }

    private function text(float $x, float $y, string $text, float $size, bool $bold, array $rgb = [0.12, 0.14, 0.18]): void
    {
        $this->pdf->SetFont('Helvetica', $bold ? 'B' : '', $size);
        $this->pdf->SetTextColor(...$this->color($rgb));
        $this->pdf->Text($x, $y, $this->encode($text));
    }

    private function rect(float $x, float $y, float $width, float $height, array $rgb): void
    {
        $this->pdf->SetFillColor(...$this->color($rgb));
        $this->pdf->Rect($x, $y, $width, $height, 'F');
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width): void
    {
        $this->pdf->SetDrawColor(...$this->color($rgb));
        $this->pdf->SetLineWidth($width);
        $this->pdf->Line($x1, $y1, $x2, $y2);
    }

    private function color(array $rgb): array
    {
        return array_map(static fn (float $value): int => (int) round($value * 255), $rgb);
    }

    private function encode(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F]/', '', $text) ?? $text;
        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private function width(string $text, float $size, bool $bold): float
    {
        $this->pdf->SetFont('Helvetica', $bold ? 'B' : '', $size);
        return $this->pdf->GetStringWidth($this->encode($text));
    }


    private function wrap(string $text, float $maxWidth, float $size, bool $bold): array
    {
        $out = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [''] as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                while ($this->width($word, $size, $bold) > $maxWidth) {
                    $cut = $word;
                    while (mb_strlen($cut) > 1 && $this->width($cut, $size, $bold) > $maxWidth) {
                        $cut = mb_substr($cut, 0, -1);
                    }
                    if ($line !== '') {
                        $out[] = $line;
                        $line = '';
                    }
                    $out[] = $cut;
                    $word = mb_substr($word, mb_strlen($cut));
                    if ($word === '') {
                        break;
                    }
                }
                if ($word === '') {
                    continue;
                }
                $candidate = $line === '' ? $word : "$line $word";
                if ($this->width($candidate, $size, $bold) <= $maxWidth) {
                    $line = $candidate;
                } else {
                    $out[] = $line;
                    $line = $word;
                }
            }
            $out[] = $line;
        }
        return $out ?: [''];
    }
}
