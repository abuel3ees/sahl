import { Head, Link, useForm } from '@inertiajs/react';
import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { PURPOSES } from '@/components/staff/application-bits';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { CertificatePurpose, Service } from '@/types/models';

type Props = {
    service: Service;
    openApplication: { id: string; reference_number: string } | null;
};

type FormData = {
    purpose: CertificatePurpose | '';
    addressed_to: string;
    certificate_language: 'ar' | 'en';
    declaration: boolean;
};

const PURPOSE_HINTS: Record<CertificatePurpose, Bilingual> = {
    employment: { en: 'A new job or a work visa', ar: 'وظيفة جديدة أو تأشيرة عمل' },
    travel: { en: 'An embassy or visa application', ar: 'سفارة أو طلب تأشيرة' },
    residency: { en: 'A residence permit, here or abroad', ar: 'تصريح إقامة هنا أو في الخارج' },
    education: { en: 'A university or scholarship', ar: 'جامعة أو منحة دراسية' },
    other: { en: 'Anything else', ar: 'أي غرض آخر' },
};

/**
 * Request a certificate in two steps: fill in, then review and submit.
 */
export default function Apply({ service, openApplication }: Props) {
    const { t } = useLocale();

    return (
        <>
            <Head title={`${t({ en: 'Request', ar: 'طلب' })}: ${t(service.name)}`} />
            <SiteHeader tone="light" />

            <main className="px-4 py-10 sm:px-8 lg:px-14 lg:py-14">
                <Link href="/account" className="mb-8 inline-flex items-center gap-2 text-[15px] font-semibold no-underline hover:text-accent">
                    <span className="rotate-180">
                        <ArrowIcon size={18} />
                    </span>
                    {t({ en: 'My account', ar: 'حسابي' })}
                </Link>

                <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                    {service.gov_entity && t(service.gov_entity.name)}
                </p>
                <h1 className="m-0 mb-10 max-w-[900px] text-[44px] leading-[1.05] tracking-[-.03em] sm:text-6xl [&:lang(ar)]:leading-[1.3]">
                    {t(service.name)}
                </h1>

                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-16">
                    {openApplication ? <AlreadyOpen application={openApplication} /> : <ApplicationForm service={service} />}
                    <ServiceFacts service={service} />
                </div>
            </main>

            <SiteFooter />
        </>
    );
}

function AlreadyOpen({ application }: { application: NonNullable<Props['openApplication']> }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col items-start gap-5 self-start border-2 border-ink px-6 py-8">
            <h2 className="m-0 text-2xl tracking-[-.02em]">{t({ en: 'You already have a request in progress', ar: 'لديك طلب قيد المعالجة' })}</h2>
            <p className="m-0 text-[17px] leading-normal">
                {t({
                    en: 'You can have one open request for this certificate at a time. Follow it until the ministry decides.',
                    ar: 'يمكنك تقديم طلب واحد مفتوح لهذه الشهادة في كل مرة. تابعه حتى تتخذ الجهة قرارها.',
                })}
            </p>
            <Link href={`/account/applications/${application.id}`} className={`${primaryButton} no-underline`}>
                <span>
                    {t({ en: 'Follow request', ar: 'متابعة الطلب' })}{' '}
                    <span dir="ltr" className="font-mono">
                        {application.reference_number}
                    </span>
                </span>
                <ArrowIcon />
            </Link>
        </div>
    );
}

/* ─────────────────────────── The form ─────────────────────────── */

function ApplicationForm({ service }: { service: Service }) {
    const { t } = useLocale();
    const [reviewing, setReviewing] = useState(false);
    const form = useForm<FormData>({ purpose: '', addressed_to: '', certificate_language: 'ar', declaration: false });
    // Not a form field: the server reports "already have an open request" against the service itself.
    const serviceError = (form.errors as Partial<Record<string, string>>).service;

    function review(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.data.purpose !== '') {
            setReviewing(true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function submit() {
        form.transform((data) => ({ ...data, addressed_to: data.addressed_to.trim() || null }));
        form.post(`/services/${service.slug}/applications`, {
            // Validation errors belong to the fields: go back to the form so they show next to them.
            onError: (errors) => setReviewing(Object.keys(errors).every((field) => field === 'declaration')),
        });
    }

    return (
        <div className="flex flex-col gap-8">
            <Steps current={reviewing ? 2 : 1} />

            {serviceError && <ErrorNotice>{serviceError}</ErrorNotice>}

            {reviewing ? (
                <Review form={form} onEdit={() => setReviewing(false)} onSubmit={submit} />
            ) : (
                <form onSubmit={review} noValidate className="flex flex-col gap-10">
                    <PurposeField value={form.data.purpose} error={form.errors.purpose} onChange={(purpose) => form.setData('purpose', purpose)} />
                    <AddresseeField value={form.data.addressed_to} error={form.errors.addressed_to} onChange={(value) => form.setData('addressed_to', value)} />
                    <LanguageField
                        value={form.data.certificate_language}
                        error={form.errors.certificate_language}
                        onChange={(language) => form.setData('certificate_language', language)}
                    />

                    <div>
                        <button type="submit" disabled={form.data.purpose === ''} className={`${primaryButton} disabled:cursor-not-allowed disabled:opacity-50`}>
                            {t({ en: 'Continue to review', ar: 'متابعة إلى المراجعة' })}
                            <ArrowIcon />
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

function Steps({ current }: { current: 1 | 2 }) {
    const { t, formatNumber } = useLocale();
    const steps: Bilingual[] = [
        { en: 'Your request', ar: 'بيانات الطلب' },
        { en: 'Review and submit', ar: 'المراجعة والتقديم' },
    ];

    return (
        <ol className="m-0 grid list-none grid-cols-2 border-b-2 border-ink p-0">
            {steps.map((step, index) => (
                <li
                    key={step.en}
                    aria-current={current === index + 1 ? 'step' : undefined}
                    className="flex items-center gap-3 py-3 text-[15px] font-semibold text-neutral-600 aria-[current=step]:text-ink"
                >
                    <span className="grid size-7 flex-none place-items-center border-2 border-current font-display text-[14px]">{formatNumber(index + 1)}</span>
                    {t(step)}
                </li>
            ))}
        </ol>
    );
}

function PurposeField({ value, error, onChange }: { value: FormData['purpose']; error?: string; onChange: (purpose: CertificatePurpose) => void }) {
    const { t } = useLocale();

    return (
        <fieldset className="m-0 border-0 p-0">
            <legend className="mb-1 p-0 text-xl font-semibold">{t({ en: 'What do you need it for?', ar: 'لأي غرض تحتاجها؟' })}</legend>
            <p className="m-0 mb-4 text-[15px] text-neutral-700">
                {t({ en: 'The purpose is printed on the certificate.', ar: 'يُطبع الغرض على الشهادة.' })}
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                {(Object.keys(PURPOSES) as CertificatePurpose[]).map((purpose) => (
                    <label
                        key={purpose}
                        className="flex cursor-pointer items-start gap-3 border-2 border-divider px-4 py-3.5 hover:border-ink has-checked:border-ink has-checked:bg-ink has-checked:text-paper"
                    >
                        <input
                            type="radio"
                            name="purpose"
                            value={purpose}
                            checked={value === purpose}
                            onChange={() => onChange(purpose)}
                            className="mt-1 size-4 flex-none accent-accent"
                        />
                        <span className="flex flex-col">
                            <span className="font-semibold">{t(PURPOSES[purpose])}</span>
                            <span className="text-[14px] opacity-75">{t(PURPOSE_HINTS[purpose])}</span>
                        </span>
                    </label>
                ))}
            </div>
            {error && <FieldError>{error}</FieldError>}
        </fieldset>
    );
}

function AddresseeField({ value, error, onChange }: { value: string; error?: string; onChange: (value: string) => void }) {
    const { t } = useLocale();
    const id = useId();

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={id} className="text-xl font-semibold">
                {t({ en: 'Who is it addressed to? (optional)', ar: 'إلى من توجَّه الشهادة؟ (اختياري)' })}
            </label>
            <p id={`${id}-hint`} className="m-0 text-[15px] text-neutral-700">
                {t({
                    en: 'For example an employer or an embassy. Leave it empty for “To whom it may concern”.',
                    ar: 'مثل جهة عمل أو سفارة. اتركه فارغاً لتكون «إلى من يهمه الأمر».',
                })}
            </p>
            <input
                id={id}
                dir="auto"
                maxLength={150}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-describedby={`${id}-hint`}
                aria-invalid={error ? true : undefined}
                className="w-full max-w-[560px] border-2 border-ink bg-paper px-4 py-3.5 text-[17px] focus:border-accent focus:outline-none aria-invalid:border-accent"
            />
            {error && <FieldError>{error}</FieldError>}
        </div>
    );
}

function LanguageField({ value, error, onChange }: { value: FormData['certificate_language']; error?: string; onChange: (language: 'ar' | 'en') => void }) {
    const { t } = useLocale();
    const options: { value: 'ar' | 'en'; label: Bilingual; lang: string }[] = [
        { value: 'ar', label: { en: 'Arabic', ar: 'العربية' }, lang: 'ar' },
        { value: 'en', label: { en: 'English', ar: 'الإنجليزية' }, lang: 'en' },
    ];

    return (
        <fieldset className="m-0 border-0 p-0">
            <legend className="mb-4 p-0 text-xl font-semibold">{t({ en: 'Certificate language', ar: 'لغة الشهادة' })}</legend>
            <div className="inline-grid grid-cols-2 border-2 border-ink">
                {options.map((option) => (
                    <label key={option.value} className="flex cursor-pointer items-center gap-2.5 px-5 py-3 font-semibold has-checked:bg-ink has-checked:text-paper">
                        <input
                            type="radio"
                            name="certificate_language"
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="size-4 accent-accent"
                        />
                        {t(option.label)}
                    </label>
                ))}
            </div>
            {error && <FieldError>{error}</FieldError>}
        </fieldset>
    );
}

/* ─────────────────────────── Review ─────────────────────────── */

function Review({ form, onEdit, onSubmit }: { form: ReturnType<typeof useForm<FormData>>; onEdit: () => void; onSubmit: () => void }) {
    const { t } = useLocale();
    const { data } = form;

    const rows: { label: Bilingual; value: ReactNode }[] = [
        { label: { en: 'Purpose', ar: 'الغرض' }, value: data.purpose ? t(PURPOSES[data.purpose]) : '' },
        {
            label: { en: 'Addressed to', ar: 'موجّهة إلى' },
            value: data.addressed_to.trim() ? <span dir="auto">{data.addressed_to.trim()}</span> : t({ en: 'To whom it may concern', ar: 'إلى من يهمه الأمر' }),
        },
        { label: { en: 'Certificate language', ar: 'لغة الشهادة' }, value: data.certificate_language === 'ar' ? t({ en: 'Arabic', ar: 'العربية' }) : t({ en: 'English', ar: 'الإنجليزية' }) },
    ];

    return (
        <div className="flex flex-col gap-8">
            <dl className="m-0 border-t-2 border-ink">
                {rows.map((row) => (
                    <div key={row.label.en} className="grid gap-1 border-b-2 border-divider py-4 sm:grid-cols-[200px_1fr] sm:gap-6">
                        <dt className="text-[14px] font-semibold text-neutral-700">{t(row.label)}</dt>
                        <dd className="m-0 text-[17px]">{row.value}</dd>
                    </div>
                ))}
            </dl>

            <label className="flex max-w-[640px] cursor-pointer items-start gap-3 text-[16px] leading-normal">
                <input
                    type="checkbox"
                    checked={data.declaration}
                    onChange={(event) => form.setData('declaration', event.target.checked)}
                    className="mt-1 size-5 flex-none cursor-pointer accent-accent"
                />
                {t({
                    en: 'I confirm these details are correct, and I allow the Ministry of Interior to check its records about me for this certificate.',
                    ar: 'أؤكد صحة هذه البيانات، وأوافق على أن تطّلع وزارة الداخلية على سجلاتها الخاصة بي لإصدار هذه الشهادة.',
                })}
            </label>
            {form.errors.declaration && <FieldError>{form.errors.declaration}</FieldError>}

            <div className="flex flex-wrap gap-3">
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={!data.declaration || form.processing}
                    className={`${primaryButton} disabled:cursor-not-allowed disabled:opacity-50`}
                >
                    {form.processing ? t({ en: 'Submitting…', ar: 'جارٍ التقديم…' }) : t({ en: 'Submit request', ar: 'تقديم الطلب' })}
                    <ArrowIcon />
                </button>
                <button type="button" onClick={onEdit} disabled={form.processing} className="cursor-pointer border-2 border-ink px-5 py-4 font-display text-[17px] font-extrabold hover:bg-ink/7">
                    {t({ en: 'Change answers', ar: 'تعديل البيانات' })}
                </button>
            </div>
        </div>
    );
}

/* ─────────────────────────── Side facts ─────────────────────────── */

function ServiceFacts({ service }: { service: Service }) {
    const { t, formatNumber } = useLocale();
    const fee = new Intl.NumberFormat(t({ en: 'en-QA', ar: 'ar-QA' }), { style: 'currency', currency: service.fee.currency }).format(service.fee.amount_minor / 100);

    const facts: { label: Bilingual; value: string }[] = [
        { label: { en: 'Processing', ar: 'مدة الإنجاز' }, value: t({ en: `Up to ${service.sla_working_days} working days`, ar: `حتى ${formatNumber(service.sla_working_days)} أيام عمل` }) },
        { label: { en: 'Fee', ar: 'الرسوم' }, value: fee },
        ...(service.validity_days
            ? [{ label: { en: 'Valid for', ar: 'مدة الصلاحية' }, value: t({ en: `${service.validity_days} days`, ar: `${formatNumber(service.validity_days)} يوماً` }) }]
            : []),
        { label: { en: 'You receive', ar: 'ستحصل على' }, value: t({ en: 'A certificate with a QR verification code', ar: 'شهادة برمز تحقق QR' }) },
    ];

    return (
        <aside className="self-start border-2 border-ink">
            <h2 className="m-0 bg-ink px-5 py-3 text-lg text-paper">{t({ en: 'About this service', ar: 'عن الخدمة' })}</h2>
            <dl className="m-0 px-5 py-2">
                {facts.map((fact) => (
                    <div key={fact.label.en} className="flex justify-between gap-4 border-b-2 border-divider py-3 last:border-0">
                        <dt className="text-[14px] font-semibold text-neutral-700">{t(fact.label)}</dt>
                        <dd className="m-0 text-end text-[15px]">{fact.value}</dd>
                    </div>
                ))}
            </dl>
            <p className="m-0 border-t-2 border-ink px-5 py-3 text-[14px] text-neutral-700">
                {t({ en: 'Demo: no payment is taken. Online payment comes in a later version.', ar: 'تجريبي: لا تُحصَّل رسوم. الدفع الإلكتروني في إصدار لاحق.' })}
            </p>
        </aside>
    );
}

/* ─────────────────────────── Bits ─────────────────────────── */

const primaryButton =
    'inline-flex cursor-pointer items-center justify-between gap-6 bg-accent px-5.5 py-4 font-display text-[17px] font-extrabold text-paper hover:bg-accent-600';

function FieldError({ children }: { children: ReactNode }) {
    return <p className="m-0 mt-2 text-[14px] font-semibold text-accent-700">{children}</p>;
}

function ErrorNotice({ children }: { children: ReactNode }) {
    return (
        <p role="alert" className="m-0 border-s-4 border-accent bg-accent-100 px-4 py-3 text-[15px] font-semibold">
            {children}
        </p>
    );
}
