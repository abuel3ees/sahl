import { Head, Link, router } from '@inertiajs/react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { useLocale, type Bilingual } from '@/lib/i18n';
import { DateText, PURPOSES, SlaBadge } from '@/components/staff/application-bits';
import type { Application, ApplicationStatus, GovEntity, Paginated } from '@/types/models';

type Props = {
    reviewer: { name: string; signed_in_with: 'entra' | 'password'; gov_entity: GovEntity };
    status: ApplicationStatus;
    counts: Record<ApplicationStatus, number>;
    applications: Paginated<Application>;
};

const TABS: { status: ApplicationStatus; label: Bilingual }[] = [
    { status: 'submitted', label: { en: 'Awaiting decision', ar: 'بانتظار القرار' } },
    { status: 'approved', label: { en: 'Approved', ar: 'مقبولة' } },
    { status: 'rejected', label: { en: 'Rejected', ar: 'مرفوضة' } },
];


/**
 * The reviewer's queue: their ministry's requests, waiting ones oldest first with the SLA clock.
 */
export default function ApplicationQueue({ reviewer, status, counts, applications }: Props) {
    const { t, formatNumber } = useLocale();
    const overdue = status === 'submitted' ? applications.data.filter((application) => application.sla?.is_overdue).length : 0;

    return (
        <>
            <Head title={t({ en: 'Review queue', ar: 'قائمة المراجعة' })} />
            <SiteHeader tone="light" currentPath="/staff" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <div className="mb-10 flex flex-wrap items-end gap-x-10 gap-y-6">
                    <div className="me-auto">
                        <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                            {t({ en: 'Back office', ar: 'المكتب الخلفي' })} · {t(reviewer.gov_entity.name)}
                        </p>
                        <h1 className="m-0 text-[44px] leading-[1.05] tracking-[-.03em] sm:text-6xl [&:lang(ar)]:leading-[1.3]">
                            {t({ en: 'Review queue', ar: 'قائمة المراجعة' })}
                        </h1>
                    </div>
                    <ReviewerCard reviewer={reviewer} />
                </div>

                <nav aria-label={t({ en: 'Request status', ar: 'حالة الطلب' })} className="flex overflow-x-auto border-b-2 border-ink">
                    {TABS.map((tab) => (
                        <Link
                            key={tab.status}
                            href={`/staff?status=${tab.status}`}
                            preserveScroll
                            aria-current={tab.status === status ? 'page' : undefined}
                            className="-mb-[2px] flex flex-none items-center gap-2.5 border-2 border-transparent px-4 py-3 text-[15px] font-semibold whitespace-nowrap no-underline hover:bg-ink/7 aria-[current=page]:border-ink aria-[current=page]:border-b-paper aria-[current=page]:bg-paper"
                        >
                            {t(tab.label)}
                            <span className="bg-surface px-2 py-0.5 font-mono text-[13px]">{formatNumber(counts[tab.status])}</span>
                        </Link>
                    ))}
                </nav>

                {overdue > 0 && (
                    <p role="status" className="m-0 mt-6 border-s-4 border-accent bg-accent-100 px-4 py-3 text-[15px]">
                        {t({
                            en: `${overdue} ${overdue === 1 ? 'request is' : 'requests are'} past the ${applications.data[0].sla?.working_days}-working-day service level. They’re at the top of the queue.`,
                            ar: `${formatNumber(overdue)} من الطلبات تجاوزت مدة الخدمة (${formatNumber(applications.data[0].sla?.working_days ?? 0)} أيام عمل)، وهي في أعلى القائمة.`,
                        })}
                    </p>
                )}

                {applications.data.length === 0 ? (
                    <EmptyState status={status} />
                ) : (
                    <>
                        <QueueTable status={status} applications={applications.data} />
                        <Pagination applications={applications} />
                    </>
                )}
            </main>

            <SiteFooter />
        </>
    );
}

function ReviewerCard({ reviewer }: { reviewer: Props['reviewer'] }) {
    const { t } = useLocale();

    return (
        <div className="flex items-center gap-5 border-2 border-divider px-4 py-3">
            <div className="flex flex-col text-[14px] leading-snug">
                <span className="font-semibold">{reviewer.name}</span>
                <span className="text-neutral-700">
                    {t({ en: 'Reviewer', ar: 'مراجع' })} ·{' '}
                    {reviewer.signed_in_with === 'entra' ? (
                        <span dir="ltr">Microsoft Entra ID</span>
                    ) : (
                        t({ en: 'Local password', ar: 'كلمة مرور محلية' })
                    )}
                </span>
            </div>
            <button
                type="button"
                onClick={() => router.post('/logout')}
                className="cursor-pointer border-2 border-ink px-3 py-1.5 text-[14px] font-semibold hover:bg-ink hover:text-paper"
            >
                {t({ en: 'Sign out', ar: 'تسجيل الخروج' })}
            </button>
        </div>
    );
}

/* ─────────────────────────── Table ─────────────────────────── */

function QueueTable({ status, applications }: { status: ApplicationStatus; applications: Application[] }) {
    const { t } = useLocale();
    const waiting = status === 'submitted';

    const headers: Bilingual[] = [
        { en: 'Reference', ar: 'رقم الطلب' },
        { en: 'Applicant', ar: 'مقدّم الطلب' },
        { en: 'Purpose', ar: 'الغرض' },
        { en: 'Submitted', ar: 'تاريخ التقديم' },
        waiting ? { en: 'Waiting', ar: 'مدة الانتظار' } : { en: 'Decided', ar: 'تاريخ القرار' },
    ];

    return (
        <div className="mt-6">
            {/* Desktop: a table. Phones: one card per row (the same cells, stacked). */}
            <table className="w-full border-collapse text-start max-md:block">
                <thead className="max-md:hidden">
                    <tr className="border-b-2 border-ink">
                        {headers.map((header) => (
                            <th key={header.en} scope="col" className="px-3 py-3 text-start text-[13px] font-semibold text-neutral-700 first:ps-0">
                                {t(header)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="max-md:flex max-md:flex-col max-md:gap-3">
                    {applications.map((application) => (
                        <QueueRow key={application.id} application={application} waiting={waiting} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function QueueRow({ application, waiting }: { application: Application; waiting: boolean }) {
    const { t } = useLocale();
    const overdue = application.sla?.is_overdue ?? false;

    return (
        <tr
            className={`border-b-2 border-divider align-top max-md:grid max-md:grid-cols-2 max-md:gap-x-4 max-md:gap-y-3 max-md:border-2 max-md:p-4 ${overdue ? 'bg-accent-100/60' : ''}`}
        >
            <td className="px-3 py-4 ps-0 max-md:col-span-2 max-md:p-0">
                <Link
                    href={`/staff/applications/${application.id}`}
                    dir="ltr"
                    className="font-mono text-[15px] font-semibold text-ink underline decoration-2 underline-offset-4 hover:text-accent"
                >
                    {application.reference_number}
                </Link>
            </td>
            <td className="px-3 py-4 max-md:col-span-2 max-md:p-0">
                <div className="font-semibold">{application.applicant && t(application.applicant.name)}</div>
                <div dir="ltr" className="font-mono text-[13px] text-neutral-700 rtl:text-end">
                    {application.applicant?.national_id}
                </div>
            </td>
            <td className="px-3 py-4 max-md:p-0">
                <div>{t(PURPOSES[application.purpose])}</div>
                {application.addressed_to && <div className="text-[13px] text-neutral-700">{application.addressed_to}</div>}
            </td>
            <td className="px-3 py-4 max-md:p-0">
                <DateText value={application.submitted_at} />
            </td>
            <td className="px-3 py-4 max-md:col-span-2 max-md:p-0">
                {waiting && application.sla ? <SlaBadge sla={application.sla} /> : application.decided_at && <DateText value={application.decided_at} />}
            </td>
        </tr>
    );
}

/* ─────────────────────────── Empty + pagination ─────────────────────────── */

function EmptyState({ status }: { status: ApplicationStatus }) {
    const { t } = useLocale();
    const messages: Record<ApplicationStatus, Bilingual> = {
        submitted: { en: 'Nothing waiting. The queue is clear.', ar: 'لا توجد طلبات بانتظار القرار.' },
        approved: { en: 'No approved requests yet.', ar: 'لا توجد طلبات مقبولة بعد.' },
        rejected: { en: 'No rejected requests.', ar: 'لا توجد طلبات مرفوضة.' },
    };

    return <p className="m-0 mt-6 border-2 border-dashed border-divider px-6 py-14 text-center text-lg text-neutral-700">{t(messages[status])}</p>;
}

function Pagination({ applications }: { applications: Paginated<Application> }) {
    const { t, formatNumber } = useLocale();
    const { meta, links } = applications;

    if (meta.last_page <= 1) {
        return null;
    }

    const pageLink = 'border-2 border-ink px-4 py-2 text-[14px] font-semibold no-underline hover:bg-ink hover:text-paper';

    return (
        <nav aria-label={t({ en: 'Pages', ar: 'الصفحات' })} className="mt-6 flex items-center gap-3">
            {links.prev && (
                <Link href={links.prev} preserveScroll className={pageLink}>
                    {t({ en: 'Previous', ar: 'السابق' })}
                </Link>
            )}
            <span className="text-[14px] text-neutral-700">
                {t({
                    en: `Page ${meta.current_page} of ${meta.last_page}`,
                    ar: `صفحة ${formatNumber(meta.current_page)} من ${formatNumber(meta.last_page)}`,
                })}
            </span>
            {links.next && (
                <Link href={links.next} preserveScroll className={pageLink}>
                    {t({ en: 'Next', ar: 'التالي' })}
                </Link>
            )}
        </nav>
    );
}
