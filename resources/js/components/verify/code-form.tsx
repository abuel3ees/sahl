import { router } from '@inertiajs/react';
import { useId, useState, type FormEvent } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { useLocale } from '@/lib/i18n';

/** Verification codes are 12 characters from an alphabet without look-alikes (no 0/O, 1/I/L). */
export const CODE_LENGTH = 12;

/** Keep letters and digits only, upper-cased, so "p3da fxx2-nsau" works as typed or pasted. */
export function normaliseCode(value: string): string {
    return value.replace(/[^a-z0-9]/gi, '').toUpperCase();
}

export function CodeForm({ initialCode = '', compact = false }: { initialCode?: string; compact?: boolean }) {
    const { t, formatNumber } = useLocale();
    const id = useId();
    const [code, setCode] = useState(initialCode);
    const [processing, setProcessing] = useState(false);
    const ready = code.length > 0;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (ready) {
            router.visit(`/verify/${code}`, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
        }
    }

    return (
        <form onSubmit={submit} className="flex w-full max-w-[640px] flex-col gap-2">
            <label htmlFor={id} className={compact ? 'text-[15px] font-semibold' : 'text-lg font-semibold'}>
                {t({ en: 'Verification code', ar: 'رمز التحقق' })}
            </label>
            <div className="flex flex-col gap-3 sm:flex-row">
                <input
                    id={id}
                    name="code"
                    dir="ltr"
                    autoComplete="off"
                    autoCapitalize="characters"
                    spellCheck={false}
                    maxLength={32}
                    value={code}
                    onChange={(event) => setCode(normaliseCode(event.target.value))}
                    placeholder="P3DAFXX2NSAU"
                    aria-describedby={`${id}-hint`}
                    className={`min-w-0 flex-1 border-2 border-ink bg-paper px-4 font-mono tracking-[.18em] uppercase placeholder:text-neutral-400 focus:border-accent focus:outline-none ${compact ? 'py-3 text-lg' : 'py-4 text-2xl'}`}
                />
                <button
                    type="submit"
                    disabled={!ready || processing}
                    className={`flex cursor-pointer items-center justify-between gap-6 bg-accent px-5.5 font-display font-extrabold text-paper hover:bg-accent-600 disabled:cursor-not-allowed disabled:opacity-50 ${compact ? 'py-3 text-[15px]' : 'py-4 text-[17px]'}`}
                >
                    {processing ? t({ en: 'Checking…', ar: 'جارٍ التحقق…' }) : t({ en: 'Check', ar: 'تحقّق' })}
                    <ArrowIcon />
                </button>
            </div>
            <p id={`${id}-hint`} className="m-0 text-[14px] text-neutral-700">
                {t({
                    en: `${CODE_LENGTH} letters and numbers, printed under the QR code on the certificate.`,
                    ar: `${formatNumber(CODE_LENGTH)} حرفاً ورقماً، مطبوعة أسفل رمز QR على الشهادة.`,
                })}
            </p>
        </form>
    );
}
