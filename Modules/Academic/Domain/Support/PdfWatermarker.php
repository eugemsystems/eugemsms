<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Stamps a single line of text across the bottom of every page of an
 * existing PDF. Built on FPDI (imports each original page as-is) +
 * FPDF (draws the new page and the stamp on top) — pure PHP, no
 * Imagick/Ghostscript system dependency. The original page content
 * (including its text layer) is preserved underneath the stamp, never
 * rasterised.
 */
final class PdfWatermarker
{
    public function stamp(string $pdfContents, string $label): string
    {
        $fpdi = new Fpdi;
        $fpdi->SetCompression(false);
        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfContents));

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $fpdi->importPage($pageNumber);
            $size = $fpdi->getTemplateSize($templateId);

            if (! is_array($size)) {
                throw new RuntimeException("Could not determine the page size of page {$pageNumber} while watermarking.");
            }

            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($templateId);

            $fpdi->SetFont('Helvetica', '', 9);
            $fpdi->SetTextColor(180, 0, 0);
            $fpdi->SetXY(5, $size['height'] - 10);
            $fpdi->Cell($size['width'] - 10, 8, $label);
        }

        /** @var string $output */
        $output = $fpdi->Output('S');

        return $output;
    }
}
