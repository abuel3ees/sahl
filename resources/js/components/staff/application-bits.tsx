import { useLocale, type Bilingual } from '@/lib/i18n';
import type { Application, ApplicationStatus, CertificatePurpose } from '@/types/models';

export const PURPOSES: Record<CertificatePurpose, Bilingual> = {
    employment: { en: 'Employment', ar: 'عمل' },
    travel: { en: 'Travel', ar: 'سفر' },
    residency: { en: 'Residency', ar: 'إقامة' },
    education: { en: 'Education', ar: 'دراسة' },
    other: { en: 'Other', ar: 'أخرى' },
};

export const STATUSES: Record<ApplicationStatus, Bilingual> = {
    submitted: { en: 'Awaiting decision', ar: 'بانتظار القرار' },
    approved: { en: 'Approved', ar: 'مقبول' },
    rejected: { en: 'Rejected', ar: 'مرفوض' },
};

/** Status as a solid label: red while waiting, ink once approved, outlined once rejected. */
export function StatusBadge({ status }: { status: ApplicationStatus }) {
    const { t } = useLocale();
    const styles: Record<ApplicationStatus, string> = {
        submitted: 'bg-accent text-paper',
        approved: 'bg-ink text-paper',
        rejected: 'border-2 border-ink',
    };

    return <span className={`inline-block px-2.5 py-1 text-[13px] font-extrabold ${styles[status]}`}>{t(STATUSES[status])}</span>;
}

export function SlaBadge({ sla }: { sla: NonNullable<Application['sla']> }) {
    const { t, formatNumber } = useLocale();
    const days = formatNumber(sla.working_days_waiting);
    const limit = formatNumber(sla.working_days);

    return (
        <span className="inline-flex flex-wrap items-center gap-2">
            <span className="text-[15px]">{t({ en: `${days} of ${limit} working days`, ar: `${days} من ${limit} أيام عمل` })}</span>
            {sla.is_overdue && (
                <span className="bg-accent px-2 py-0.5 text-[12px] font-extrabold tracking-[.06em] text-paper uppercase [&:lang(ar)]:tracking-normal">
                    {t({ en: 'Overdue', ar: 'متأخر' })}
                </span>
            )}
        </span>
    );
}

export function DateText({ value, withTime = false }: { value: string; withTime?: boolean }) {
    const { locale } = useLocale();
    const options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric', ...(withTime && { hour: 'numeric', minute: '2-digit' }) };

    return (
        <time dateTime={value} className="text-[15px]">
            {new Intl.DateTimeFormat(locale === 'ar' ? 'ar-QA' : 'en-GB', options).format(new Date(value))}
        </time>
    );
}
