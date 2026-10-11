import { Head } from '@inertiajs/react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { CodeForm } from '@/components/verify/code-form';
import { useLocale, type Bilingual } from '@/lib/i18n';

const STEPS: { title: Bilingual; body: Bilingual }[] = [
    {
        title: { en: 'Scan or type', ar: 'امسح أو اكتب' },
        body: { en: 'Scan the QR code on the certificate, or type the code printed under it.', ar: 'امسح رمز QR على الشهادة، أو اكتب الرمز المطبوع أسفله.' },
    },
    {
        title: { en: 'Compare', ar: 'قارن' },
        body: {
            en: 'Check the name, the last four digits of the National ID and the dates against the paper or PDF you were given.',
            ar: 'طابق الاسم وآخر أربعة أرقام من رقم الهوية والتواريخ مع النسخة الورقية أو ملف PDF الذي استلمته.',
        },
    },
    {
        title: { en: 'Trust the record', ar: 'اعتمد السجل' },
        body: {
            en: 'If anything differs, the record here is the official one.',
            ar: 'إن اختلف أي شيء، فالسجل الظاهر هنا هو المعتمد.',
        },
    },
];

/**
 * Public certificate check. No sign-in: employers and embassies use it.
 */
export default function VerifyIndex() {
    const { t, formatNumber } = useLocale();

    return (
        <>
            <Head title={t({ en: 'Verify a certificate', ar: 'التحقق من شهادة' })} />
            <SiteHeader tone="light" currentPath="/verify" />

            <main>
                <section className="border-b-2 border-divider px-4 py-14 sm:px-8 lg:px-14 lg:py-24">
                    <p className="m-0 mb-4 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                        {t({ en: 'Certificate verification', ar: 'التحقق من الشهادات' })}
                    </p>
                    <h1 className="m-0 mb-6 max-w-[900px] text-[48px] leading-[.98] tracking-[-.04em] sm:text-7xl [&:lang(ar)]:leading-[1.25]">
                        {t({ en: 'Is this certificate genuine?', ar: 'هل هذه الشهادة صحيحة؟' })}
                    </h1>
                    <p className="m-0 mb-10 max-w-[620px] text-lg leading-normal text-neutral-800">
                        {t({
                            en: 'Every certificate Sahl issues carries a verification code. Enter it to see the official record. No account needed.',
                            ar: 'تحمل كل شهادة يصدرها سهل رمز تحقق. أدخله لعرض السجل الرسمي، دون الحاجة إلى حساب.',
                        })}
                    </p>
                    <CodeForm />
                </section>

                <ol className="m-0 grid list-none gap-0 p-0 md:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <li key={step.title.en} className="border-b-2 border-divider px-4 py-10 sm:px-8 md:border-e-2 md:last:border-e-0 lg:px-14">
                            <span className="mb-4 block font-display text-5xl font-extrabold text-accent">{formatNumber(index + 1)}</span>
                            <h2 className="m-0 mb-2 text-2xl tracking-[-.02em]">{t(step.title)}</h2>
                            <p className="m-0 text-[16px] leading-normal text-neutral-800">{t(step.body)}</p>
                        </li>
                    ))}
                </ol>
            </main>

            <SiteFooter />
        </>
    );
}
