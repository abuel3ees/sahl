import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { DateText } from '@/components/staff/application-bits';
import { CodeForm } from '@/components/verify/code-form';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { CertificateVerification } from '@/types/models';

type Props = {
    code: string;
    certificate: CertificateVerification | null;
};

/**
 * The result of a certificate check: valid, expired, or no such certificate.
 */
export default function VerifyShow({ code, certificate }: Props) {
    const { t } = useLocale();

    return (
        <>
            <Head title={t({ en: 'Certificate check', ar: 'نتيجة التحقق' })}>
                {/* Results name a person: keep them out of search engines. */}
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <SiteHeader tone="light" currentPath="/verify" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <Link href="/verify" className="mb-8 inline-flex items-center gap-2 text-[15px] font-semibold no-underline hover:text-accent">
                    <span className="rotate-180">
                        <ArrowIcon size={18} />
                    </span>
                    {t({ en: 'Verify a certificate', ar: 'التحقق من شهادة' })}
                </Link>

                {certificate ? <Result certificate={certificate} code={code} /> : <NotFound code={code} />}

                <section className="mt-16 border-t-2 border-ink pt-8">
                    <h2 className="m-0 mb-5 text-2xl tracking-[-.02em]">{t({ en: 'Check another certificate', ar: 'تحقق من شهادة أخرى' })}</h2>
                    <CodeForm compact />
                </section>
            </main>

            <SiteFooter />
        </>
    );
}

/* ─────────────────────────── Found ─────────────────────────── */

function Result({ certificate, code }: { certificate: CertificateVerification; code: string }) {
    const { t } = useLocale();
    const valid = certificate.status === 'valid';

    return (
        <>
            <div className={`flex flex-col gap-6 px-6 py-8 sm:flex-row sm:items-center sm:px-10 sm:py-10 ${valid ? 'bg-ink text-paper' : 'bg-accent text-paper'}`}>
                <StatusMark valid={valid} />
                <div className="flex flex-col gap-2">
                    <p className="m-0 font-display text-sm font-extrabold tracking-[.12em] uppercase opacity-80 [&:lang(ar)]:tracking-normal">
                        {t(certificate.service)}
                    </p>
                    <h1 className="m-0 text-[36px] leading-[1.05] tracking-[-.03em] sm:text-5xl [&:lang(ar)]:leading-[1.3]">
                        {valid
                            ? t({ en: 'Genuine and valid.', ar: 'شهادة صحيحة وسارية.' })
                            : t({ en: 'Genuine, but expired.', ar: 'شهادة صحيحة لكنها منتهية.' })}
                    </h1>
                    <p className="m-0 text-[17px] leading-normal">
                        {valid
                            ? t({
                                  en: 'This certificate was issued through Sahl and has not expired.',
                                  ar: 'صدرت هذه الشهادة عبر سهل ولم تنتهِ صلاحيتها.',
                              })
                            : t({
                                  en: 'This certificate was issued through Sahl, but its validity period has ended. Ask the holder for a new one.',
                                  ar: 'صدرت هذه الشهادة عبر سهل، لكن مدة صلاحيتها انتهت. اطلب من صاحبها شهادة جديدة.',
                              })}
                    </p>
                </div>
            </div>

            <dl className="m-0 mt-2 grid border-b-2 border-divider md:grid-cols-2">
                <Detail label={{ en: 'Holder', ar: 'صاحب الشهادة' }}>
                    <span lang="ar" className="block text-lg font-semibold">
                        {certificate.holder_name.ar}
                    </span>
                    <span lang="en" className="block">
                        {certificate.holder_name.en}
                    </span>
                </Detail>
                <Detail label={{ en: 'National ID (last 4 digits)', ar: 'رقم الهوية (آخر ٤ أرقام)' }}>
                    <span dir="ltr" className="font-mono text-lg tracking-[.08em]">
                        {certificate.holder_national_id_masked}
                    </span>
                </Detail>
                <Detail label={{ en: 'Issued by', ar: 'الجهة المصدرة' }}>{t(certificate.issuer)}</Detail>
                <Detail label={{ en: 'Serial number', ar: 'الرقم التسلسلي' }}>
                    <span dir="ltr" className="font-mono">
                        {certificate.serial_number}
                    </span>
                </Detail>
                <Detail label={{ en: 'Issued', ar: 'تاريخ الإصدار' }}>
                    <DateText value={certificate.issued_at} />
                </Detail>
                <Detail label={{ en: 'Valid until', ar: 'صالحة حتى' }}>
                    {certificate.expires_at ? <DateText value={certificate.expires_at} /> : t({ en: 'No expiry', ar: 'بلا انتهاء' })}
                </Detail>
                <Detail label={{ en: 'Verification code', ar: 'رمز التحقق' }}>
                    <span dir="ltr" className="font-mono tracking-[.12em]">
                        {code}
                    </span>
                </Detail>
            </dl>

            <p className="m-0 mt-5 max-w-[760px] text-[14px] text-neutral-700">
                {t({
                    en: 'Only what’s needed to confirm the certificate is shown. The full National ID and the reason for the request are never published.',
                    ar: 'لا يُعرض إلا ما يلزم لتأكيد الشهادة. لا يُنشر رقم الهوية كاملاً ولا الغرض من الطلب.',
                })}
            </p>
        </>
    );
}

function Detail({ label, children }: { label: Bilingual; children: ReactNode }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-1 border-t-2 border-divider py-4 md:odd:pe-8 md:even:border-s-2 md:even:ps-8">
            <dt className="text-[14px] font-semibold text-neutral-700">{t(label)}</dt>
            <dd className="m-0 text-[16px]">{children}</dd>
        </div>
    );
}

/** A large check (valid) or clock (expired) in a square. */
function StatusMark({ valid }: { valid: boolean }) {
    return (
        <span aria-hidden="true" className="grid size-20 flex-none place-items-center border-2 border-paper sm:size-24">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="square">
                {valid ? <path d="m4 12.5 5 5L20 6.5" /> : <path d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />}
            </svg>
        </span>
    );
}

/* ─────────────────────────── Not found ─────────────────────────── */

function NotFound({ code }: { code: string }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-6 border-2 border-dashed border-ink px-6 py-8 sm:flex-row sm:items-center sm:px-10 sm:py-10">
            <span aria-hidden="true" className="grid size-20 flex-none place-items-center border-2 border-ink font-display text-5xl font-extrabold sm:size-24">
                ?
            </span>
            <div className="flex flex-col gap-3">
                <h1 className="m-0 text-[36px] leading-[1.05] tracking-[-.03em] sm:text-5xl [&:lang(ar)]:leading-[1.3]">
                    {t({ en: 'No certificate matches this code.', ar: 'لا توجد شهادة بهذا الرمز.' })}
                </h1>
                <p className="m-0 text-[17px] leading-normal">
                    {t({ en: 'You entered', ar: 'أدخلت' })}{' '}
                    <span dir="ltr" className="bg-surface px-2 py-0.5 font-mono tracking-[.12em]">
                        {code}
                    </span>
                    .{' '}
                    {t({
                        en: 'Check it against the certificate. Codes never contain 0, O, 1, I or L. If it still doesn’t match, don’t rely on the document.',
                        ar: 'طابقه مع الشهادة. لا تحتوي الرموز أبداً على 0 أو O أو 1 أو I أو L. إن لم يتطابق، فلا تعتمد على المستند.',
                    })}
                </p>
            </div>
        </div>
    );
}
