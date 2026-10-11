import { DateText } from '@/components/staff/application-bits';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { TimelineEntry } from '@/types/models';

/** Events are worded for whoever is reading: the applicant ("You submitted") or a reviewer ("Submitted by the applicant"). */
const EVENTS: Record<'citizen' | 'staff', Record<TimelineEntry['to_status'], Bilingual>> = {
    staff: {
        submitted: { en: 'Submitted by the applicant', ar: 'قدّمه مقدّم الطلب' },
        approved: { en: 'Approved by the ministry · certificate issued', ar: 'قبلته الجهة · صدرت الشهادة' },
        rejected: { en: 'Rejected by the ministry', ar: 'رفضته الجهة' },
    },
    citizen: {
        submitted: { en: 'You submitted your request', ar: 'قدّمت طلبك' },
        approved: { en: 'Approved · your certificate was issued', ar: 'تم القبول · صدرت شهادتك' },
        rejected: { en: 'Not approved', ar: 'لم يُقبل الطلب' },
    },
};

export function Timeline({ entries, viewer }: { entries: TimelineEntry[]; viewer: 'citizen' | 'staff' }) {
    const { t } = useLocale();

    return (
        <ol className="m-0 list-none p-0">
            {entries.map((entry, index) => (
                <li key={`${entry.to_status}-${entry.at}`} className="relative flex gap-4 pt-5">
                    {/* The square marker and the rule that joins it to the next event. */}
                    <span aria-hidden="true" className="relative flex w-3 flex-none justify-center">
                        <span
                            className={`mt-1.5 size-3 flex-none ${entry.to_status === 'rejected' ? 'border-2 border-ink' : entry.to_status === 'approved' ? 'bg-ink' : 'bg-accent'}`}
                        />
                        {index < entries.length - 1 && <span className="absolute top-6 -bottom-6 w-0.5 bg-divider" />}
                    </span>
                    <div className="flex flex-col gap-1 pb-1">
                        <span className="font-semibold">{t(EVENTS[viewer][entry.to_status])}</span>
                        <span className="text-[14px] text-neutral-700">
                            <DateText value={entry.at} withTime />
                        </span>
                        {entry.note && (
                            <p dir="auto" className="m-0 mt-1 border-s-2 border-divider ps-3 text-start text-[15px]">
                                {entry.note}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
