import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Timeline } from '@/components/applications/timeline';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { DateText, PURPOSES, StatusBadge } from '@/components/staff/application-bits';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { Application } from '@/types/models';

type Props = {
    application: Application;
    decisionDueBy: string | null;
};

/**
 * A citizen follows one of their requests: where it is, what happens next, and the certificate once issued.
 */
export default function CitizenApplication({ application, decisionDueBy }: Props) {
    const { t } = useLocale();
    const justSubmitted = usePage().flash.submitted === true;

    return (
        <>
            <Head title={`${application.reference_number} · ${t({ en: 'My requests', ar: 'طلباتي' })}`} />
            <SiteHeader tone="light" currentPath="/account" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <Link href="/account" className="mb-8 inline-flex items-center gap-2 text-[15px] font-semibold no-underline hover:text-accent">
                    <span className="rotate-180">
                        <ArrowIcon size={18} />
                    </span>
                    {t({ en: 'My account', ar: 'حسابي' })}
                </Link>

                {justSubmitted && (
                    <div role="status" className="mb-10 flex flex-col gap-2 bg-ink px-6 py-6 text-paper sm:px-8">
                        <p className="m-0 font-display text-2xl font-extrabold">{t({ en: 'Request submitted.', ar: 'تم تقديم الطلب.' })}</p>
                        <p className="m-0 text-[16px]">
                            {t({
                                en: 'Keep your reference number. The Ministry of Interior has been notified and will review it.',
                                ar: 'احتفظ برقم الطلب. تم إشعار وزارة الداخلية وستراجعه.',
                            })}
                        </p>
                    </div>
                )}

                <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                    {application.service && t(application.service.name)}
                </p>
                <div className="mb-10 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <h1 dir="ltr" className="m-0 font-mono text-[32px] leading-tight tracking-[-.02em] sm:text-[44px]">
                        {application.reference_number}
                    </h1>
                    <StatusBadge status={application.status} />
                </div>

                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-16">
                    <div className="flex flex-col gap-12">
                        <NextStep application={application} decisionDueBy={decisionDueBy} />
                        <Section title={{ en: 'Your request', ar: 'طلبك' }}>
                            <Rows
                                rows={[
                                    { label: { en: 'Purpose', ar: 'الغرض' }, value: t(PURPOSES[application.purpose]) },
                                    {
                                        label: { en: 'Addressed to', ar: 'موجّهة إلى' },
                                        value: application.addressed_to ? (
                                            <span dir="auto">{application.addressed_to}</span>
                                        ) : (
                                            t({ en: 'To whom it may concern', ar: 'إلى من يهمه الأمر' })
                                        ),
                                    },
                                    {
                                        label: { en: 'Certificate language', ar: 'لغة الشهادة' },
                                        value: application.certificate_language === 'ar' ? t({ en: 'Arabic', ar: 'العربية' }) : t({ en: 'English', ar: 'الإنجليزية' }),
                                    },
                                    { label: { en: 'Submitted', ar: 'تاريخ التقديم' }, value: <DateText value={application.submitted_at} withTime /> },
                                ]}
                            />
                        </Section>
                    </div>

                    {application.timeline && (
                        <aside>
                            <Section title={{ en: 'Progress', ar: 'مراحل الطلب' }}>
                                <Timeline entries={application.timeline} viewer="citizen" />
                            </Section>
                        </aside>
                    )}
                </div>
            </main>

            <SiteFooter />
        </>
    );
}

/** The one thing the citizen should know now, depending on where the request is. */
function NextStep({ application, decisionDueBy }: Props) {
    const { t } = useLocale();

    if (application.status === 'submitted') {
        return (
            <Callout tone="waiting" title={{ en: 'With the Ministry of Interior', ar: 'لدى وزارة الداخلية' }}>
                {t({ en: 'A reviewer will decide by ', ar: 'سيُتخذ القرار بحلول ' })}
                {decisionDueBy && (
                    <strong>
                        <DateText value={decisionDueBy} />
                    </strong>
                )}
                {t({
                    en: ' (working days, Sunday to Thursday). You don’t need to do anything.',
                    ar: ' (أيام العمل من الأحد إلى الخميس). لا يلزمك فعل أي شيء.',
                })}
            </Callout>
        );
    }

    if (application.status === 'rejected') {
        return (
            <Callout tone="rejected" title={{ en: 'Your request was not approved', ar: 'لم يُقبل طلبك' }}>
                <span className="block">{t({ en: 'The ministry gave this reason:', ar: 'ذكرت الجهة السبب التالي:' })}</span>
                <span dir="auto" className="mt-2 block border-s-2 border-ink ps-3 font-semibold">
                    {application.decision_note}
                </span>
                <span className="mt-3 block">
                    {t({ en: 'You can fix this and ', ar: 'يمكنك معالجة ذلك ثم ' })}
                    <Link href={`/services/${application.service?.slug}/apply`} className="font-semibold text-ink underline decoration-2 underline-offset-4 hover:text-accent">
                        {t({ en: 'request again', ar: 'التقديم من جديد' })}
                    </Link>
                    .
                </span>
            </Callout>
        );
    }

    const certificate = application.certificate;

    return (
        <Callout tone="approved" title={{ en: 'Your certificate is ready', ar: 'شهادتك جاهزة' }}>
            {certificate && (
                <>
                    <span className="block">
                        {t({
                            en: 'Give the verification code to whoever needs the certificate. They can check it online.',
                            ar: 'أعطِ رمز التحقق لمن يحتاج الشهادة، ويمكنه التحقق منها إلكترونياً.',
                        })}
                    </span>
                    <span className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2">
                        <span>
                            {t({ en: 'Verification code', ar: 'رمز التحقق' })}:{' '}
                            <span dir="ltr" className="bg-paper px-2 py-0.5 font-mono font-semibold tracking-[.12em] text-ink">
                                {certificate.verification_code}
                            </span>
                        </span>
                        <span>
                            {t({ en: 'Valid until', ar: 'صالحة حتى' })}:{' '}
                            {certificate.expires_at ? <DateText value={certificate.expires_at} /> : t({ en: 'No expiry', ar: 'بلا انتهاء' })}
                        </span>
                    </span>
                    <span className="mt-5 flex flex-wrap gap-3">
                        <a
                            href={`/applications/${application.id}/certificate.pdf`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-3 bg-accent px-4 py-2.5 font-display font-extrabold text-paper no-underline hover:bg-accent-600"
                        >
                            {t({ en: 'Download certificate (PDF)', ar: 'تنزيل الشهادة (PDF)' })}
                            <ArrowIcon size={18} />
                        </a>
                        <a
                            href={certificate.verification_url}
                            className="inline-flex items-center gap-3 border-2 border-paper px-4 py-2 font-semibold text-paper no-underline hover:bg-paper hover:text-ink"
                        >
                            {t({ en: 'Open the verification page', ar: 'فتح صفحة التحقق' })}
                        </a>
                    </span>
                </>
            )}
        </Callout>
    );
}

function Callout({ tone, title, children }: { tone: 'waiting' | 'approved' | 'rejected'; title: Bilingual; children: ReactNode }) {
    const { t } = useLocale();
    const styles = {
        waiting: 'border-s-4 border-accent bg-accent-100',
        approved: 'bg-ink text-paper',
        rejected: 'border-2 border-ink',
    };

    return (
        <section className={`px-6 py-6 sm:px-8 ${styles[tone]}`}>
            <h2 className="m-0 mb-3 text-2xl tracking-[-.02em]">{t(title)}</h2>
            <div className="text-[16px] leading-relaxed">{children}</div>
        </section>
    );
}

function Section({ title, children }: { title: Bilingual; children: ReactNode }) {
    const { t } = useLocale();

    return (
        <section>
            <h2 className="m-0 border-b-2 border-ink pb-3 text-2xl tracking-[-.02em]">{t(title)}</h2>
            {children}
        </section>
    );
}

function Rows({ rows }: { rows: { label: Bilingual; value: ReactNode }[] }) {
    const { t } = useLocale();

    return (
        <dl className="m-0">
            {rows.map((row) => (
                <div key={row.label.en} className="grid gap-1 border-b-2 border-divider py-3.5 sm:grid-cols-[190px_1fr] sm:gap-6">
                    <dt className="text-[14px] font-semibold text-neutral-700">{t(row.label)}</dt>
                    <dd className="m-0 text-[16px]">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}
