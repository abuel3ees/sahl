import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Timeline } from '@/components/applications/timeline';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { DateText, PURPOSES, SlaBadge, StatusBadge } from '@/components/staff/application-bits';
import { DecisionPanel } from '@/components/staff/decision-panel';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { Application, Certificate, TimelineEntry } from '@/types/models';

type Props = {
    application: Application;
    decidedBy: string | null;
    canDecide: boolean;
};

/**
 * One request in the back office: who is asking, for what, what has happened so far, and the certificate once issued.
 */
export default function ApplicationShow({ application, decidedBy, canDecide }: Props) {
    const { t } = useLocale();
    const waiting = application.status === 'submitted';

    return (
        <>
            <Head title={`${application.reference_number} · ${t({ en: 'Back office', ar: 'المكتب الخلفي' })}`} />
            <SiteHeader tone="light" currentPath="/staff" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <Link
                    href={`/staff?status=${application.status}`}
                    className="mb-8 inline-flex items-center gap-2 text-[15px] font-semibold no-underline hover:text-accent"
                >
                    <span className="rotate-180">
                        <ArrowIcon size={18} />
                    </span>
                    {t({ en: 'Review queue', ar: 'قائمة المراجعة' })}
                </Link>

                <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                    {application.service && t(application.service.name)}
                </p>
                <div className="mb-10 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <h1 dir="ltr" className="m-0 font-mono text-[32px] leading-tight tracking-[-.02em] sm:text-[44px]">
                        {application.reference_number}
                    </h1>
                    <StatusBadge status={application.status} />
                    {waiting && application.sla && <SlaBadge sla={application.sla} />}
                </div>

                <DecisionBanner application={application} />

                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-16">
                    <div className="flex flex-col gap-12">
                        <ApplicantSection application={application} />
                        <RequestSection application={application} />
                        {!waiting && <DecisionSection application={application} decidedBy={decidedBy} />}
                    </div>

                    <aside className="flex flex-col gap-12">
                        {canDecide && <DecisionPanel application={application} />}
                        {application.timeline && <TimelineSection entries={application.timeline} />}
                        {application.certificate && <CertificateCard certificate={application.certificate} />}
                    </aside>
                </div>
            </main>

            <SiteFooter />
        </>
    );
}

/** The result of a decision made a moment ago (flashed by the server for one page view). */
function DecisionBanner({ application }: { application: Application }) {
    const { t } = useLocale();
    const decision = usePage().flash.decision;

    if (!decision) {
        return null;
    }

    const messages = {
        approved: {
            en: `Approved. Certificate ${application.certificate?.serial_number ?? ''} is issued and the applicant can download it.`,
            ar: `تم القبول. صدرت الشهادة ${application.certificate?.serial_number ?? ''} ويمكن لمقدّم الطلب تنزيلها.`,
        },
        rejected: { en: 'Rejected. The applicant will see your reason.', ar: 'تم الرفض. سيرى مقدّم الطلب السبب الذي كتبته.' },
        already_decided: {
            en: 'A colleague decided this request first, so nothing was changed. Their decision is shown below.',
            ar: 'سبقك زميل إلى اتخاذ القرار، فلم يتغيّر شيء. قراره ظاهر أدناه.',
        },
    } satisfies Record<typeof decision, Bilingual>;

    return (
        <p role="status" className={`m-0 mb-10 border-s-4 px-4 py-3 text-[15px] font-semibold ${decision === 'already_decided' ? 'border-accent bg-accent-100' : 'border-ink bg-surface'}`}>
            {t(messages[decision])}
        </p>
    );
}

/* ─────────────────────────── Sections ─────────────────────────── */

function Section({ title, children }: { title: Bilingual; children: ReactNode }) {
    const { t } = useLocale();

    return (
        <section>
            <h2 className="m-0 border-b-2 border-ink pb-3 text-2xl tracking-[-.02em]">{t(title)}</h2>
            {children}
        </section>
    );
}

type Row = { label: Bilingual; value: ReactNode; ltr?: boolean };

function Rows({ rows }: { rows: Row[] }) {
    const { t } = useLocale();

    return (
        <dl className="m-0">
            {rows.map((row) => (
                <div key={row.label.en} className="grid gap-1 border-b-2 border-divider py-3.5 sm:grid-cols-[190px_1fr] sm:gap-6">
                    <dt className="text-[14px] font-semibold text-neutral-700">{t(row.label)}</dt>
                    <dd className="m-0 text-[16px]">{row.ltr ? <span dir="ltr">{row.value}</span> : row.value}</dd>
                </div>
            ))}
        </dl>
    );
}

function ApplicantSection({ application }: { application: Application }) {
    const applicant = application.applicant;

    if (!applicant) {
        return null;
    }

    return (
        <Section title={{ en: 'Applicant', ar: 'مقدّم الطلب' }}>
            <Rows
                rows={[
                    // Both spellings: the certificate prints the name in the language the applicant chose.
                    { label: { en: 'Name (Arabic)', ar: 'الاسم بالعربية' }, value: <span lang="ar">{applicant.name.ar}</span> },
                    { label: { en: 'Name (English)', ar: 'الاسم بالإنجليزية' }, value: <span lang="en">{applicant.name.en}</span> },
                    { label: { en: 'National ID', ar: 'رقم الهوية' }, value: <span className="font-mono">{applicant.national_id}</span>, ltr: true },
                    { label: { en: 'Email', ar: 'البريد الإلكتروني' }, value: applicant.email, ltr: true },
                ]}
            />
        </Section>
    );
}

function RequestSection({ application }: { application: Application }) {
    const { t } = useLocale();

    return (
        <Section title={{ en: 'Request', ar: 'الطلب' }}>
            <Rows
                rows={[
                    { label: { en: 'Purpose', ar: 'الغرض' }, value: t(PURPOSES[application.purpose]) },
                    {
                        label: { en: 'Addressed to', ar: 'موجّهة إلى' },
                        value: application.addressed_to ? <span dir="auto">{application.addressed_to}</span> : (
                            <span className="text-neutral-700">{t({ en: 'To whom it may concern', ar: 'إلى من يهمه الأمر' })}</span>
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
    );
}

function DecisionSection({ application, decidedBy }: { application: Application; decidedBy: string | null }) {
    const { t } = useLocale();
    const approved = application.status === 'approved';

    return (
        <Section title={{ en: 'Decision', ar: 'القرار' }}>
            <Rows
                rows={[
                    { label: { en: 'Outcome', ar: 'النتيجة' }, value: <StatusBadge status={application.status} /> },
                    ...(application.decided_at ? [{ label: { en: 'Decided', ar: 'تاريخ القرار' }, value: <DateText value={application.decided_at} withTime /> }] : []),
                    ...(decidedBy ? [{ label: { en: 'Decided by', ar: 'صاحب القرار' }, value: decidedBy }] : []),
                    ...(application.decision_note
                        ? [
                              {
                                  label: approved ? { en: 'Note', ar: 'ملاحظة' } : { en: 'Reason (shown to the applicant)', ar: 'السبب (يظهر لمقدّم الطلب)' },
                                  // Free text typed by a person: let the browser pick its direction (an English reason inside the Arabic page).
                                  value: <span dir="auto">{application.decision_note}</span>,
                              },
                          ]
                        : []),
                ]}
            />
        </Section>
    );
}

function TimelineSection({ entries }: { entries: TimelineEntry[] }) {
    return (
        <Section title={{ en: 'Timeline', ar: 'السجل' }}>
            <Timeline entries={entries} viewer="staff" />
        </Section>
    );
}

/* ─────────────────────────── Certificate ─────────────────────────── */

function CertificateCard({ certificate }: { certificate: Certificate }) {
    const { t } = useLocale();

    return (
        <section className="border-2 border-ink">
            <div className="flex items-center justify-between gap-4 bg-ink px-5 py-3 text-paper">
                <h2 className="m-0 text-lg">{t({ en: 'Certificate issued', ar: 'الشهادة الصادرة' })}</h2>
                {certificate.is_expired && (
                    <span className="bg-accent px-2 py-0.5 text-[12px] font-extrabold">{t({ en: 'Expired', ar: 'منتهية' })}</span>
                )}
            </div>
            <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-5 gap-y-3 px-5 py-5 text-[15px]">
                <dt className="font-semibold text-neutral-700">{t({ en: 'Serial', ar: 'الرقم التسلسلي' })}</dt>
                <dd dir="ltr" className="m-0 font-mono rtl:text-end">
                    {certificate.serial_number}
                </dd>
                <dt className="font-semibold text-neutral-700">{t({ en: 'Verification code', ar: 'رمز التحقق' })}</dt>
                <dd dir="ltr" className="m-0 font-mono tracking-[.08em] rtl:text-end">
                    {certificate.verification_code}
                </dd>
                <dt className="font-semibold text-neutral-700">{t({ en: 'Issued', ar: 'تاريخ الإصدار' })}</dt>
                <dd className="m-0">
                    <DateText value={certificate.issued_at} />
                </dd>
                <dt className="font-semibold text-neutral-700">{t({ en: 'Valid until', ar: 'صالحة حتى' })}</dt>
                <dd className="m-0">
                    {certificate.expires_at ? <DateText value={certificate.expires_at} /> : t({ en: 'No expiry', ar: 'بلا انتهاء' })}
                </dd>
            </dl>
            <a
                href={certificate.verification_url}
                target="_blank"
                rel="noreferrer"
                className="flex items-center justify-between gap-4 border-t-2 border-ink px-5 py-3 text-[15px] font-semibold no-underline hover:bg-ink hover:text-paper"
            >
                {t({ en: 'Open the public verification record', ar: 'فتح سجل التحقق العام' })}
                <ArrowIcon size={18} />
            </a>
        </section>
    );
}
