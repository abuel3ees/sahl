import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { DateText, PURPOSES, StatusBadge } from '@/components/staff/application-bits';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { Application } from '@/types/models';

type Props = {
    citizen: { name: string; name_ar: string | null; email: string | null; national_id: string };
    applications: Application[];
};

/**
 * The signed-in citizen's home: identity as Sahl knows it, and their requests.
 */
export default function Account({ citizen, applications }: Props) {
    const { t, locale } = useLocale();
    const displayName = locale === 'ar' && citizen.name_ar ? citizen.name_ar : citizen.name;

    return (
        <>
            <Head title={t({ en: 'My account', ar: 'حسابي' })} />
            <SiteHeader tone="light" currentPath="/account" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <div className="mb-12 flex flex-wrap items-end gap-x-10 gap-y-6">
                    <div className="me-auto">
                        <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                            {t({ en: 'My account', ar: 'حسابي' })}
                        </p>
                        <h1 className="m-0 text-[44px] leading-[1.05] tracking-[-.03em] sm:text-6xl [&:lang(ar)]:leading-[1.3]">
                            {t({ en: `Hello, ${displayName}.`, ar: `أهلاً، ${displayName}.` })}
                        </h1>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="cursor-pointer border-2 border-ink px-4 py-2.5 text-[15px] font-semibold hover:bg-ink hover:text-paper"
                    >
                        {t({ en: 'Sign out', ar: 'تسجيل الخروج' })}
                    </button>
                </div>

                <div className="grid gap-12 lg:grid-cols-[360px_minmax(0,1fr)] lg:gap-16">
                    <section>
                        <h2 className="m-0 border-b-2 border-ink pb-3 text-2xl tracking-[-.02em]">{t({ en: 'Your details', ar: 'بياناتك' })}</h2>
                        <dl className="m-0">
                            <Row label={{ en: 'National ID', ar: 'رقم الهوية' }}>
                                <span dir="ltr" className="font-mono">
                                    {citizen.national_id}
                                </span>
                            </Row>
                            <Row label={{ en: 'Email', ar: 'البريد الإلكتروني' }}>
                                <span dir="ltr">{citizen.email ?? '—'}</span>
                            </Row>
                            <Row label={{ en: 'Signed in with', ar: 'طريقة الدخول' }}>{t({ en: 'Sahl ID', ar: 'هوية سهل' })}</Row>
                        </dl>
                    </section>

                    <section>
                        <div className="flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-3">
                            <h2 className="m-0 text-2xl tracking-[-.02em]">{t({ en: 'Your requests', ar: 'طلباتك' })}</h2>
                            <Link
                                href="/services/good-conduct-certificate/apply"
                                className="inline-flex items-center gap-3 bg-accent px-4 py-2.5 font-display text-[15px] font-extrabold text-paper no-underline hover:bg-accent-600"
                            >
                                {t({ en: 'Request a Good Conduct Certificate', ar: 'طلب شهادة حسن السيرة والسلوك' })}
                                <ArrowIcon size={18} />
                            </Link>
                        </div>
                        {applications.length === 0 ? (
                            <p className="m-0 mt-6 border-2 border-dashed border-divider px-6 py-12 text-center text-lg text-neutral-700">
                                {t({ en: 'No requests yet.', ar: 'لا توجد طلبات بعد.' })}
                            </p>
                        ) : (
                            <ul className="m-0 list-none p-0">
                                {applications.map((application) => (
                                    <RequestRow key={application.id} application={application} />
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </main>

            <SiteFooter />
        </>
    );
}

function Row({ label, children }: { label: Bilingual; children: ReactNode }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-1 border-b-2 border-divider py-3.5">
            <dt className="text-[14px] font-semibold text-neutral-700">{t(label)}</dt>
            <dd className="m-0 text-[16px]">{children}</dd>
        </div>
    );
}

function RequestRow({ application }: { application: Application }) {
    const { t } = useLocale();

    return (
        <li className="flex flex-wrap items-start gap-x-6 gap-y-3 border-b-2 border-divider py-5">
            <div className="me-auto flex flex-col gap-1">
                <Link
                    href={`/account/applications/${application.id}`}
                    dir="ltr"
                    className="font-mono text-[15px] font-semibold text-ink underline decoration-2 underline-offset-4 hover:text-accent rtl:text-end"
                >
                    {application.reference_number}
                </Link>
                <span className="font-semibold">{application.service && t(application.service.name)}</span>
                <span className="text-[14px] text-neutral-700">
                    {t(PURPOSES[application.purpose])} · <DateText value={application.submitted_at} />
                </span>
                {application.status === 'rejected' && application.decision_note && (
                    <p dir="auto" className="m-0 mt-2 max-w-[560px] border-s-2 border-accent ps-3 text-start text-[15px]">
                        {application.decision_note}
                    </p>
                )}
            </div>
            <div className="flex flex-col items-start gap-2">
                <StatusBadge status={application.status} />
                {application.certificate && (
                    <a
                        href={application.certificate.verification_url}
                        className="inline-flex items-center gap-2 text-[14px] font-semibold text-ink underline decoration-2 underline-offset-4 hover:text-accent"
                    >
                        {t({ en: 'Certificate record', ar: 'سجل الشهادة' })}
                        <ArrowIcon size={16} />
                    </a>
                )}
            </div>
        </li>
    );
}
