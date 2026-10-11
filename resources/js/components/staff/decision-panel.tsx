import { useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';
import { useLocale } from '@/lib/i18n';
import type { Application } from '@/types/models';

type Outcome = 'approve' | 'reject';

/** Mirrors RejectApplicationRequest: a reason of 10–1,000 characters. */
const REASON_MIN = 10;
const TEXT_MAX = 1000;

/**
 * Approve or reject a waiting request. Two steps on purpose: write the note or reason, then confirm,
 * because a decision can't be undone and an approval issues a certificate straight away.
 */
export function DecisionPanel({ application }: { application: Application }) {
    const { t } = useLocale();
    const [outcome, setOutcome] = useState<Outcome | null>(null);

    return (
        <section className="border-2 border-ink">
            <div className="bg-ink px-5 py-3 text-paper">
                <h2 className="m-0 text-lg">{t({ en: 'Your decision', ar: 'قرارك' })}</h2>
            </div>

            <div className="flex flex-col gap-5 px-5 py-5">
                {outcome === null ? (
                    <>
                        <p className="m-0 text-[15px] text-neutral-700">
                            {t({
                                en: 'Check the applicant and the request, then approve or reject. The applicant is told either way.',
                                ar: 'راجع بيانات مقدّم الطلب والطلب، ثم اقبل أو ارفض. سيُبلَّغ مقدّم الطلب في الحالتين.',
                            })}
                        </p>
                        <div className="grid grid-cols-2 gap-3">
                            <button type="button" onClick={() => setOutcome('approve')} className={`${buttonBase} bg-ink text-paper hover:bg-neutral-800`}>
                                {t({ en: 'Approve', ar: 'قبول' })}
                            </button>
                            <button type="button" onClick={() => setOutcome('reject')} className={`${buttonBase} border-2 border-ink hover:bg-ink/7`}>
                                {t({ en: 'Reject', ar: 'رفض' })}
                            </button>
                        </div>
                    </>
                ) : (
                    <DecisionForm key={outcome} outcome={outcome} application={application} onCancel={() => setOutcome(null)} />
                )}
            </div>
        </section>
    );
}

function DecisionForm({ outcome, application, onCancel }: { outcome: Outcome; application: Application; onCancel: () => void }) {
    const { t, formatNumber } = useLocale();
    const textId = useId();
    const approving = outcome === 'approve';
    const field = approving ? 'note' : 'reason';
    const form = useForm<Record<'note' | 'reason', string>>({ note: '', reason: '' });
    const [confirming, setConfirming] = useState(false);
    const text = form.data[field];
    const tooShort = !approving && text.trim().length < REASON_MIN;
    const error = form.errors[field];

    function review(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!tooShort) {
            setConfirming(true);
        }
    }

    function submit() {
        const path = approving ? 'approval' : 'rejection';

        form.transform((data) => (approving ? { note: data.note.trim() || null } : { reason: data.reason.trim() }));
        form.post(`/staff/applications/${application.id}/${path}`, {
            preserveScroll: true,
            onError: () => setConfirming(false),
        });
    }

    return (
        <form onSubmit={review} noValidate className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
                <label htmlFor={textId} className="text-[15px] font-semibold">
                    {approving
                        ? t({ en: 'Note for the applicant (optional)', ar: 'ملاحظة لمقدّم الطلب (اختياري)' })
                        : t({ en: 'Reason for rejecting', ar: 'سبب الرفض' })}
                </label>
                <textarea
                    id={textId}
                    dir="auto"
                    rows={5}
                    maxLength={TEXT_MAX}
                    value={text}
                    disabled={confirming}
                    onChange={(event) => form.setData(field, event.target.value)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={`${textId}-hint`}
                    className="w-full resize-y border-2 border-ink bg-paper px-3.5 py-3 text-[16px] focus:border-accent focus:outline-none disabled:bg-surface aria-invalid:border-accent"
                />
                <p id={`${textId}-hint`} className="m-0 flex justify-between gap-4 text-[13px] text-neutral-700">
                    <span>
                        {approving
                            ? t({ en: 'Shown to the applicant with their certificate.', ar: 'تظهر لمقدّم الطلب مع شهادته.' })
                            : t({
                                  en: `Shown to the applicant. At least ${REASON_MIN} characters: say what is missing and what to do next.`,
                                  ar: `تظهر لمقدّم الطلب. ${formatNumber(REASON_MIN)} أحرف على الأقل: وضّح ما الناقص وما الخطوة التالية.`,
                              })}
                    </span>
                    <span className="flex-none font-mono">
                        {formatNumber(text.length)}/{formatNumber(TEXT_MAX)}
                    </span>
                </p>
                {error && <p className="m-0 text-[14px] font-semibold text-accent-700">{error}</p>}
            </div>

            {confirming ? (
                <div role="alertdialog" aria-labelledby={`${textId}-confirm`} className="flex flex-col gap-3 border-s-4 border-accent bg-accent-100 px-4 py-3">
                    <p id={`${textId}-confirm`} className="m-0 text-[15px] font-semibold">
                        {approving
                            ? t({
                                  en: `Approve ${application.reference_number} and issue the certificate? This can’t be undone.`,
                                  ar: `قبول الطلب ${application.reference_number} وإصدار الشهادة؟ لا يمكن التراجع عن ذلك.`,
                              })
                            : t({
                                  en: `Reject ${application.reference_number}? This can’t be undone; the applicant would need to apply again.`,
                                  ar: `رفض الطلب ${application.reference_number}؟ لا يمكن التراجع، وسيحتاج مقدّم الطلب إلى التقديم من جديد.`,
                              })}
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={submit}
                            disabled={form.processing}
                            className={`${buttonBase} bg-accent text-paper hover:bg-accent-600 disabled:opacity-60`}
                        >
                            {form.processing
                                ? t({ en: 'Saving…', ar: 'جارٍ الحفظ…' })
                                : approving
                                  ? t({ en: 'Yes, approve and issue', ar: 'نعم، اقبل وأصدر الشهادة' })
                                  : t({ en: 'Yes, reject', ar: 'نعم، ارفض الطلب' })}
                        </button>
                        <button type="button" onClick={() => setConfirming(false)} disabled={form.processing} className={`${buttonBase} border-2 border-ink hover:bg-ink/7`}>
                            {t({ en: 'Go back', ar: 'رجوع' })}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        disabled={tooShort}
                        className={`${buttonBase} ${approving ? 'bg-ink text-paper hover:bg-neutral-800' : 'bg-accent text-paper hover:bg-accent-600'} disabled:cursor-not-allowed disabled:opacity-50`}
                    >
                        {approving ? t({ en: 'Continue to approve', ar: 'متابعة القبول' }) : t({ en: 'Continue to reject', ar: 'متابعة الرفض' })}
                    </button>
                    <button type="button" onClick={onCancel} className={`${buttonBase} hover:bg-ink/7`}>
                        {t({ en: 'Cancel', ar: 'إلغاء' })}
                    </button>
                </div>
            )}
        </form>
    );
}

const buttonBase = 'cursor-pointer px-4 py-3 font-display text-[15px] font-extrabold';
