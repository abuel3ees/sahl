<?php

namespace App\Certificates;

use App\Enums\CertificatePurpose;
use App\Models\Certificate;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders a certificate as an A4 PDF from its issue-time snapshot, so the same certificate always prints the same.
 *
 * mPDF is used because it shapes Arabic (joined letters, right-to-left) in pure PHP. The QR code opens the public
 * verification page, which is what makes the paper trustworthy in V1. Later: generate once at issue, sign with a
 * key in Azure Key Vault, and keep the file in immutable Blob Storage.
 */
class CertificatePdf
{
    private const FONT = 'plexarabic';

    public function render(Certificate $certificate): string
    {
        $content = $certificate->content;
        $language = $content['language'] === 'en' ? 'en' : 'ar';

        $html = view('certificates.good-conduct', [
            'certificate' => $certificate,
            'language' => $language,
            'holderName' => $language === 'ar' ? ($content['holder_name_ar'] ?: $content['holder_name_en']) : $content['holder_name_en'],
            'purpose' => CertificatePurpose::from($content['purpose'])->label()[$language],
            'addressedTo' => $content['addressed_to'] ?? null,
            'verificationUrl' => route('verify.show', $certificate->verification_code),
        ])->render();

        $pdf = $this->mpdf($language);
        $pdf->SetTitle("{$content["service_name_{$language}"]} {$certificate->serial_number}");
        $pdf->SetAuthor($content["issuer_name_{$language}"]);
        $pdf->SetCreator('Sahl (fictional demonstration)');
        $pdf->WriteHTML($html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function mpdf(string $language): Mpdf
    {
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'tempDir' => $tempDir,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], resource_path('fonts/ibm-plex-sans-arabic')],
            'fontdata' => [
                ...(new FontVariables)->getDefaults()['fontdata'],
                self::FONT => [
                    'R' => 'IBMPlexSansArabic-Regular.ttf',
                    'B' => 'IBMPlexSansArabic-Bold.ttf',
                    'useOTL' => 0xFF, // OpenType layout: Arabic letter joining and ligatures
                    'useKashida' => 75,
                ],
            ],
            'default_font' => self::FONT,
            'directionality' => $language === 'ar' ? 'rtl' : 'ltr',
            'autoLangToFont' => false,
        ]);

        // Never mistakable for a real document.
        $pdf->SetWatermarkText($language === 'ar' ? 'نموذج تجريبي' : 'DEMONSTRATION', 0.06);
        $pdf->showWatermarkText = true;
        $pdf->watermark_font = self::FONT;

        return $pdf;
    }
}
