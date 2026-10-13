@php
    /*
     * Good Conduct Certificate, rendered by mPDF (App\Certificates\CertificatePdf).
     * mPDF understands a subset of HTML 4 and CSS 2: layout is done with tables and inline styles.
     * Colours are the Sahl tokens: accent #ec3013, ink #201e1d, neutral #605d5d.
     */
    $ar = $language === 'ar';
    $content = $certificate->content;
    $date = fn ($value) => $value?->locale($language)->translatedFormat('j F Y');
    $t = fn (string $en, string $arabic) => $ar ? $arabic : $en;
    $star = 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#ffffff"/>'
        .'<rect x="11" y="11" width="18" height="18" fill="none" stroke="#ec3013" stroke-width="2.4"/>'
        .'<rect x="11" y="11" width="18" height="18" fill="none" stroke="#ec3013" stroke-width="2.4" transform="rotate(45 20 20)"/></svg>'
    );
@endphp
<html dir="{{ $ar ? 'rtl' : 'ltr' }}" lang="{{ $language }}">
<head>
<style>
    body { font-family: plexarabic; color: #201e1d; font-size: 11pt; }
    .band { background-color: #ec3013; color: #ffffff; }
    .muted { color: #605d5d; }
    .label { color: #605d5d; font-size: 9pt; }
    .rule { border-bottom: 1.5pt solid #201e1d; }
    .thin { border-bottom: 0.75pt solid #b8b5b5; }
    .mono { font-family: dejavusansmono; letter-spacing: 1pt; }
</style>
</head>
<body>

{{-- Header: the State and the Ministry, always in both languages, laid out the same way in both (English left, Arabic right) --}}
<table width="100%" cellpadding="0" cellspacing="0" class="band" dir="ltr" style="padding: 5mm 6mm;">
    <tr>
        <td width="14mm" style="padding: 4mm;"><img src="{{ $star }}" width="12mm" height="12mm" /></td>
        <td style="padding: 4mm 2mm; text-align: left;" dir="ltr">
            <div style="font-size: 13pt; font-weight: bold;">State of Al-Wahah</div>
            <div style="font-size: 10pt;">Ministry of Interior</div>
        </td>
        <td style="padding: 4mm; text-align: right;" dir="rtl">
            <div style="font-size: 13pt; font-weight: bold;">دولة الواحة</div>
            <div style="font-size: 10pt;">وزارة الداخلية</div>
        </td>
    </tr>
</table>

{{-- Title --}}
<div style="margin-top: 12mm; font-size: 9pt; font-weight: bold; color: #ec3013; letter-spacing: {{ $ar ? '0' : '1.5pt' }};">
    {{ $t('OFFICIAL CERTIFICATE', 'شهادة رسمية') }}
</div>
<div style="font-size: 28pt; font-weight: bold; line-height: 1.2;">
    {{ $content[$ar ? 'service_name_ar' : 'service_name_en'] }}
</div>

{{-- Serial and dates --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 6mm; border-top: 1.5pt solid #201e1d;">
    <tr>
        <td width="40%" style="padding-top: 3mm;">
            <div class="label">{{ $t('Serial number', 'الرقم التسلسلي') }}</div>
            <div class="mono" dir="ltr" style="font-size: 11pt;">{{ $certificate->serial_number }}</div>
        </td>
        <td width="30%" style="padding-top: 3mm;">
            <div class="label">{{ $t('Date of issue', 'تاريخ الإصدار') }}</div>
            <div>{{ $date($certificate->issued_at) }}</div>
        </td>
        <td width="30%" style="padding-top: 3mm;">
            <div class="label">{{ $t('Valid until', 'صالحة حتى') }}</div>
            <div>{{ $certificate->expires_at ? $date($certificate->expires_at) : $t('No expiry', 'بلا انتهاء') }}</div>
        </td>
    </tr>
</table>

{{-- The statement --}}
<div style="margin-top: 12mm; font-size: 13pt; line-height: 1.8; text-align: justify;">
    @if ($ar)
        تشهد وزارة الداخلية في دولة الواحة بأن <b>{{ $holderName }}</b>، حامل الهوية الوطنية رقم
        <b><span dir="ltr">{{ $content['holder_national_id'] }}</span></b>،
        ليس لديه سجل جنائي لدى الوزارة حتى تاريخ إصدار هذه الشهادة.
    @else
        The Ministry of Interior of the State of Al-Wahah certifies that <b>{{ $holderName }}</b>,
        holder of National ID number <b>{{ $content['holder_national_id'] }}</b>,
        has no criminal record with the Ministry as of the date of issue of this certificate.
    @endif
</div>

{{-- Details --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 10mm;">
    <tr>
        <td width="35%" class="thin label" style="padding: 3mm 0;">{{ $t('Holder', 'صاحب الشهادة') }}</td>
        <td class="thin" style="padding: 3mm 0;">
            {{ $content['holder_name_ar'] }}@if ($content['holder_name_ar'] && $content['holder_name_en']) &nbsp;·&nbsp; @endif<span dir="ltr">{{ $content['holder_name_en'] }}</span>
        </td>
    </tr>
    <tr>
        <td class="thin label" style="padding: 3mm 0;">{{ $t('National ID', 'رقم الهوية الوطنية') }}</td>
        <td class="thin" style="padding: 3mm 0;"><span class="mono" dir="ltr">{{ $content['holder_national_id'] }}</span></td>
    </tr>
    <tr>
        <td class="thin label" style="padding: 3mm 0;">{{ $t('Purpose', 'الغرض') }}</td>
        <td class="thin" style="padding: 3mm 0;">{{ $purpose }}</td>
    </tr>
    <tr>
        <td class="thin label" style="padding: 3mm 0;">{{ $t('Addressed to', 'موجّهة إلى') }}</td>
        <td class="thin" style="padding: 3mm 0;">{{ $addressedTo ?: $t('To whom it may concern', 'إلى من يهمه الأمر') }}</td>
    </tr>
    <tr>
        <td class="thin label" style="padding: 3mm 0;">{{ $t('Issued by', 'الجهة المصدرة') }}</td>
        <td class="thin" style="padding: 3mm 0;">{{ $content[$ar ? 'issuer_name_ar' : 'issuer_name_en'] }}</td>
    </tr>
</table>

{{-- Verification: the QR code opens the official record --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 14mm; border: 1.5pt solid #201e1d;">
    <tr>
        <td width="40mm" style="padding: 4mm;">
            <barcode code="{{ $verificationUrl }}" type="QR" size="1.1" error="M" disableborder="1" />
        </td>
        <td style="padding: 4mm 5mm;">
            <div style="font-size: 12pt; font-weight: bold;">{{ $t('Verify this certificate', 'تحقّق من هذه الشهادة') }}</div>
            <div class="muted" style="margin-top: 2mm; line-height: 1.6;">
                {{ $t('Scan the code, or open the address below and enter the verification code. If the details shown there differ from this document, do not rely on it.', 'امسح الرمز، أو افتح العنوان أدناه وأدخل رمز التحقق. إن اختلفت البيانات الظاهرة هناك عن هذا المستند، فلا تعتمد عليه.') }}
            </div>
            <div style="margin-top: 3mm;">
                <span class="label">{{ $t('Verification code', 'رمز التحقق') }}:</span>
                <span class="mono" dir="ltr" style="font-size: 13pt; font-weight: bold;">{{ $certificate->verification_code }}</span>
            </div>
            <div class="mono muted" dir="ltr" style="margin-top: 1mm; font-size: 8.5pt;">{{ $verificationUrl }}</div>
        </td>
    </tr>
</table>

{{-- Footer --}}
<div class="muted" style="margin-top: 10mm; font-size: 8.5pt; line-height: 1.6;">
    {{ $t('Issued electronically through Sahl. It needs no signature or stamp: the online record is the proof.', 'صدرت إلكترونياً عبر سهل، ولا تحتاج إلى توقيع أو ختم: السجل الإلكتروني هو الإثبات.') }}<br />
    <b style="color: #ec3013;">{{ $t('Fictional demonstration. The State of Al-Wahah and this certificate are not real.', 'نموذج تجريبي. دولة الواحة وهذه الشهادة غير حقيقيتين.') }}</b>
</div>

</body>
</html>
