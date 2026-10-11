import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { SolidStar } from '@/components/site/star-mark';
import { useLocale, type Bilingual } from '@/lib/i18n';
import { usePrefersReducedMotion } from '@/lib/motion';
import type { Service } from '@/types/models';

type Props = {
    featuredService: Service | null;
};

export default function Landing({ featuredService }: Props) {
    const { t } = useLocale();

    return (
        <>
            <Head title={t({ en: 'Government, made simple', ar: 'الحكومة، ببساطة' })} />
            <Hero />
            <PhotoMarquee />
            <ServiceTicker />
            {featuredService && <FeaturedService service={featuredService} />}
            <SiteFooter />
        </>
    );
}

/* ─────────────────────────── Hero ─────────────────────────── */

function Hero() {
    const { t } = useLocale();

    return (
        <section className="relative flex min-h-[760px] flex-col overflow-hidden bg-accent text-paper lg:h-[860px]">
            <SiteHeader tone="red" currentPath="/" />

            {/* A slowly turning eight-point star, drawn as four outlined squares. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -end-[420px] -top-[60px] size-[1100px] opacity-[.22] motion-safe:animate-spin-slow lg:-end-[260px]"
            >
                <div className="absolute inset-[150px] border-2 border-paper" />
                <div className="absolute inset-[150px] rotate-45 border-2 border-paper" />
                <div className="absolute inset-[290px] rotate-[22.5deg] border-2 border-paper" />
                <div className="absolute inset-[290px] rotate-[67.5deg] border-2 border-paper" />
            </div>

            {/* The monumental Kufic wordmark, revealed top-down. */}
            <div aria-hidden="true" className="pointer-events-none absolute inset-y-0 end-0 w-full overflow-hidden lg:w-[600px]">
                <div
                    dir="rtl"
                    lang="ar"
                    className="absolute end-4 -bottom-[36px] font-kufi text-[150px] leading-none font-bold whitespace-nowrap text-paper motion-safe:animate-wipe motion-safe:[animation-delay:300ms] sm:-bottom-[58px] sm:text-[220px] lg:end-10 lg:-bottom-[110px] lg:text-[430px]"
                >
                    سهل
                </div>
            </div>

            <div className="relative z-10 flex max-w-[720px] flex-col gap-7 px-4 pt-14 sm:px-8 lg:px-14 lg:pt-[88px]">
                <h1 className="m-0 text-[56px] leading-[.95] tracking-[-.04em] text-paper motion-safe:animate-rise motion-safe:[animation-delay:400ms] sm:text-7xl lg:text-[92px] [&:lang(ar)]:leading-[1.25]">
                    {t({ en: 'Government, made simple.', ar: 'الحكومة، ببساطة.' })}
                </h1>
                <p className="m-0 max-w-[540px] text-lg leading-normal motion-safe:animate-rise motion-safe:[animation-delay:550ms] lg:text-[21px]">
                    {t({
                        en: 'One secure account for every ministry. Request official documents, follow each step, and receive them signed and verifiable.',
                        ar: 'حساب آمن واحد لكل الوزارات. اطلب وثائقك الرسمية، وتابع كل خطوة، واستلمها موقّعة وقابلة للتحقق.',
                    })}
                </p>
                <div className="flex motion-safe:animate-rise motion-safe:[animation-delay:700ms]">
                    <a
                        href="/login"
                        className="inline-flex min-w-[300px] items-center justify-between gap-6 bg-paper px-5.5 py-4 font-display text-[17px] font-extrabold text-accent-700 no-underline hover:bg-accent-100"
                    >
                        {t({ en: 'Sign in with National ID', ar: 'الدخول بالهوية الوطنية' })}
                        <ArrowIcon />
                    </a>
                </div>
            </div>

            {/* Dictionary entry for the name. */}
            <div className="relative z-10 mx-4 mt-auto mb-[150px] flex flex-col gap-1 border-s-2 border-paper ps-4 text-[15px] motion-safe:animate-rise motion-safe:[animation-delay:1.4s] sm:mx-8 sm:mb-[200px] lg:mx-14 lg:mb-12">
                <span className="font-kufi text-[28px] leading-[1.2]" lang="ar">
                    سَهْل{' '}
                    <span lang="en" className="font-sans text-lg">
                        /sahl/
                    </span>
                </span>
                <span>
                    {t({
                        en: 'adj. — easy, effortless; n. — open, level ground.',
                        ar: 'صفة — يسير، لا مشقّة فيه؛ اسم — أرض منبسطة.',
                    })}
                </span>
            </div>
        </section>
    );
}

/* ─────────────────────── Photo marquee ─────────────────────── */

/**
 * Black-and-white photography strip. Drop images into public/images/marquee/ and set `src`;
 * until then each tile shows a hatched placeholder with its caption.
 */
const PHOTOS: { caption: Bilingual; src?: string }[] = [
    { caption: { en: 'Old town skyline at dusk', ar: 'أفق المدينة القديمة عند الغروب' } },
    { caption: { en: 'A citizen applying from home', ar: 'مواطن يقدّم طلبه من المنزل' } },
    { caption: { en: 'Ministry building façade', ar: 'واجهة مبنى الوزارة' } },
    { caption: { en: 'A family at a café, phone in hand', ar: 'عائلة في مقهى والهاتف في اليد' } },
    { caption: { en: 'The corniche in the morning', ar: 'الكورنيش في الصباح' } },
    { caption: { en: 'An officer at a review desk', ar: 'موظف على مكتب المراجعة' } },
];

function PhotoMarquee() {
    const { t, locale } = useLocale();

    return (
        <div dir="ltr" className="overflow-hidden border-b-2 border-divider">
            <div className="flex w-max motion-safe:animate-marquee">
                {[0, 1].map((copy) => (
                    <div key={copy} className="flex" aria-hidden={copy === 1 ? true : undefined}>
                        {PHOTOS.map((photo) => (
                            <figure
                                key={photo.caption.en}
                                className="relative h-[220px] w-[280px] flex-none border-e-2 border-paper bg-[repeating-linear-gradient(135deg,var(--color-neutral-300)_0_2px,var(--color-neutral-200)_2px_14px)] sm:h-[340px] sm:w-[420px]"
                            >
                                {photo.src && <img src={photo.src} alt="" className="size-full object-cover grayscale contrast-[1.08]" />}
                                <figcaption className={`absolute inset-x-4 bottom-4 text-xs text-neutral-700 ${locale === 'en' ? 'font-mono' : ''}`}>
                                    {t(photo.caption)}
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ─────────────────────── Service ticker ─────────────────────── */

const TICKER: Bilingual[] = [
    { en: 'Good Conduct Certificate', ar: 'شهادة حسن السيرة والسلوك' },
    { en: 'Commercial Registration', ar: 'السجل التجاري' },
    { en: 'Medical Fitness', ar: 'اللياقة الطبية' },
    { en: 'Trade Names', ar: 'الأسماء التجارية' },
];

function ServiceTicker() {
    const { t } = useLocale();

    return (
        <div dir="ltr" className="overflow-hidden border-b-2 border-divider py-9">
            <div className="flex w-max motion-safe:animate-marquee-fast">
                {[0, 1].map((copy) => (
                    <div key={copy} className="flex items-center" aria-hidden={copy === 1 ? true : undefined}>
                        {[...TICKER, ...TICKER].map((item, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-10 pe-10 font-display text-4xl font-extrabold tracking-[-.03em] whitespace-nowrap lg:text-[56px]"
                            >
                                <span>{t(item)}</span>
                                <SolidStar />
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ─────────────────────── Featured service ─────────────────────── */

function FeaturedService({ service }: { service: Service }) {
    const { t, formatNumber } = useLocale();
    const ministry = service.gov_entity?.name ?? { en: '', ar: '' };
    const days = service.sla_working_days;

    const facts: { label: Bilingual; value: Bilingual }[] = [
        { label: { en: 'Issued by', ar: 'جهة الإصدار' }, value: ministry },
        {
            label: { en: 'Processing', ar: 'مدة الإنجاز' },
            value: { en: `Up to ${days} working days`, ar: `حتى ${formatNumber(days)} أيام عمل` },
        },
        {
            label: { en: 'Fee', ar: 'الرسوم' },
            value:
                service.fee.amount_minor === 0
                    ? { en: 'No fee', ar: 'بدون رسوم' }
                    : {
                          en: new Intl.NumberFormat('en-QA', { style: 'currency', currency: service.fee.currency }).format(service.fee.amount_minor / 100),
                          ar: new Intl.NumberFormat('ar-QA', { style: 'currency', currency: service.fee.currency }).format(service.fee.amount_minor / 100),
                      },
        },
        { label: { en: 'You receive', ar: 'المُخرَج' }, value: { en: 'Signed PDF with QR code', ar: 'ملف PDF موقّع مع رمز QR' } },
    ];

    return (
        <section id="featured-service" className="grid scroll-mt-4 border-b-2 border-divider lg:grid-cols-2">
            <div className="flex flex-col gap-7 border-divider px-4 py-16 sm:px-8 lg:border-e-2 lg:px-14 lg:py-20">
                <span className="text-[13px] font-semibold tracking-[.14em] text-accent-700 uppercase">
                    {t({ en: 'Featured service', ar: 'خدمة مميّزة' })} · {t(ministry)}
                </span>
                <h2 className="m-0 text-5xl leading-none tracking-[-.035em] lg:text-[64px] [&:lang(ar)]:leading-[1.3]">{t(service.name)}</h2>
                <dl className="m-0 grid grid-cols-[140px_1fr] border-t-2 border-divider text-[17px] sm:grid-cols-[200px_1fr]">
                    {facts.map((fact) => (
                        <div key={fact.label.en} className="contents">
                            <dt className="border-b border-divider py-3.5 text-neutral-700">{t(fact.label)}</dt>
                            <dd className="m-0 border-b border-divider py-3.5 font-semibold">{t(fact.value)}</dd>
                        </div>
                    ))}
                </dl>
                <a
                    href={`/services/${service.slug}/apply`}
                    className="inline-flex min-w-[280px] items-center justify-between gap-6 self-start bg-accent px-5.5 py-4 font-display text-[17px] font-extrabold text-paper no-underline hover:bg-accent-600 active:bg-accent-700"
                >
                    {t({ en: 'Start request', ar: 'ابدأ الطلب' })}
                    <ArrowIcon />
                </a>
            </div>
            <div id="track" className="flex scroll-mt-4 flex-col justify-center bg-surface px-4 py-16 sm:px-8 lg:px-14 lg:py-20">
                <LiveStatusCard />
            </div>
        </section>
    );
}

/* ─────────────────────── Live status demo ─────────────────────── */

const STATUS_STEPS: { label: Bilingual; actor: Bilingual; time: string }[] = [
    { label: { en: 'Request submitted', ar: 'تم استلام الطلب' }, actor: { en: 'Citizen', ar: 'المواطن' }, time: '09:14' },
    { label: { en: 'Under review', ar: 'قيد المراجعة' }, actor: { en: 'Ministry officer', ar: 'موظف الوزارة' }, time: '10:02' },
    { label: { en: 'Approved', ar: 'تمت الموافقة' }, actor: { en: 'Ministry officer', ar: 'موظف الوزارة' }, time: '13:47' },
    { label: { en: 'Certificate issued', ar: 'صدرت الشهادة' }, actor: { en: 'Sahl', ar: 'سهل' }, time: '13:48' },
];

/** Six ticks per loop: four steps, then two ticks resting on "issued" before restarting. */
const TICKS_PER_LOOP = 6;

/**
 * A demonstration of request tracking: steps light up one by one, like a real request moving
 * through review. Holds on the final step for visitors who prefer reduced motion.
 */
function LiveStatusCard() {
    const { t } = useLocale();
    const reducedMotion = usePrefersReducedMotion();
    const [tick, setTick] = useState(0);

    useEffect(() => {
        if (reducedMotion) {
            return;
        }

        const timer = window.setInterval(() => setTick((current) => (current + 1) % TICKS_PER_LOOP), 1500);

        return () => window.clearInterval(timer);
    }, [reducedMotion]);

    const lastStep = STATUS_STEPS.length - 1;
    const step = reducedMotion ? lastStep : Math.min(tick, lastStep);

    return (
        <div className="flex flex-col gap-7 bg-paper p-6 shadow-md sm:p-8">
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-1">
                    <span className="text-[13px] text-neutral-700">
                        {t({ en: 'Request', ar: 'رقم الطلب' })} · <span className="font-mono">SHL-2026-4F7KQ2</span>
                    </span>
                    <span className="font-display text-[26px] font-extrabold" aria-live="polite">
                        {t(STATUS_STEPS[step].label)}
                    </span>
                </div>
                <span className="flex items-center gap-2 text-[13px] font-semibold text-accent-700">
                    <span aria-hidden="true" className="size-2 bg-accent motion-safe:animate-blink" />
                    {t({ en: 'Live', ar: 'مباشر' })}
                </span>
            </div>

            <div className="relative h-1 bg-neutral-300">
                <div
                    className="absolute inset-y-0 start-0 bg-accent transition-[width] duration-800 ease-[cubic-bezier(.6,0,.3,1)]"
                    style={{ width: `${(step / lastStep) * 100}%` }}
                />
            </div>

            <ol className="m-0 flex list-none flex-col p-0">
                {STATUS_STEPS.map((item, index) => {
                    const reached = index <= step;

                    return (
                        <li
                            key={item.label.en}
                            className={`grid grid-cols-[24px_1fr_auto] items-center gap-4 border-b border-divider py-3.5 transition-colors duration-400 ${reached ? 'text-ink' : 'text-neutral-500'}`}
                        >
                            <span
                                aria-hidden="true"
                                className={`size-4 border-2 transition-all duration-400 ${reached ? 'border-accent bg-accent' : 'border-neutral-400 bg-transparent'}`}
                            />
                            <span className="flex flex-col">
                                <span className="text-[17px] font-semibold">{t(item.label)}</span>
                                <span className="text-[13px]">{t(item.actor)}</span>
                            </span>
                            <span className="font-mono text-sm">{reached ? item.time : '—'}</span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
