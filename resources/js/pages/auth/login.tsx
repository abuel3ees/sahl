import { Head, Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { StarMark } from '@/components/site/star-mark';
import { useLocale, type Bilingual } from '@/lib/i18n';
import type { SignInError } from '@/types/global';

type Audience = 'citizen' | 'staff';

const AUDIENCES: { value: Audience; label: Bilingual }[] = [
    { value: 'citizen', label: { en: 'Citizens & residents', ar: 'المواطنون والمقيمون' } },
    { value: 'staff', label: { en: 'Government staff', ar: 'موظفو الجهات' } },
];

/**
 * Sign-in screen. Citizens continue to Entra External ID ("Sahl ID"), staff to Microsoft Entra ID.
 */
export default function Login() {
    const { t } = useLocale();

    return (
        <>
            <Head title={t({ en: 'Sign in', ar: 'تسجيل الدخول' })} />
            <div className="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <PosterPanel />
                <main className="flex flex-col">
                    <TopBar />
                    <div className="flex flex-1 items-start justify-center px-4 py-10 sm:px-8 lg:items-center lg:px-14 lg:py-16">
                        <SignInForm />
                    </div>
                    <p className="m-0 border-t-2 border-divider px-4 py-4 text-[13px] text-neutral-700 sm:px-8 lg:px-14">
                        {t({
                            en: 'Fictional demonstration. The State of Al-Wahah and every account here are made up.',
                            ar: 'نموذج تجريبي. دولة الواحة وجميع الحسابات هنا خيالية.',
                        })}
                    </p>
                </main>
            </div>
        </>
    );
}

/* ─────────────────────────── Poster ─────────────────────────── */

function PosterPanel() {
    const { t } = useLocale();

    return (
        <aside className="relative hidden overflow-hidden bg-accent text-paper lg:flex lg:flex-col">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -end-[380px] -top-[120px] size-[900px] opacity-[.22] motion-safe:animate-spin-slow"
            >
                <div className="absolute inset-[120px] border-2 border-paper" />
                <div className="absolute inset-[120px] rotate-45 border-2 border-paper" />
                <div className="absolute inset-[240px] rotate-[22.5deg] border-2 border-paper" />
                <div className="absolute inset-[240px] rotate-[67.5deg] border-2 border-paper" />
            </div>

            <Link href="/" className="relative z-10 flex items-center gap-3.5 px-14 pt-10 text-paper no-underline">
                <StarMark inverted />
                <span className="font-display text-[22px] font-extrabold">{t({ en: 'Sahl', ar: 'سهل' })}</span>
            </Link>

            <div className="relative z-10 mt-auto flex max-w-[460px] flex-col gap-5 px-14 pb-[250px]">
                <h2 className="m-0 text-[56px] leading-[.95] tracking-[-.04em] motion-safe:animate-rise [&:lang(ar)]:leading-[1.25]">
                    {t({ en: 'One account. Every ministry.', ar: 'حساب واحد. لكل الوزارات.' })}
                </h2>
                <p className="m-0 text-lg leading-normal motion-safe:animate-rise motion-safe:[animation-delay:150ms]">
                    {t({
                        en: 'Sign in once to request official documents and follow every step.',
                        ar: 'سجّل الدخول مرة واحدة لطلب وثائقك الرسمية ومتابعة كل خطوة.',
                    })}
                </p>
            </div>

            <div
                aria-hidden="true"
                dir="rtl"
                lang="ar"
                className="pointer-events-none absolute end-8 -bottom-[70px] font-kufi text-[260px] leading-none font-bold whitespace-nowrap motion-safe:animate-wipe motion-safe:[animation-delay:200ms]"
            >
                سهل
            </div>
        </aside>
    );
}

/* ─────────────────────────── Top bar ─────────────────────────── */

function TopBar() {
    const { t, toggleLocale } = useLocale();

    return (
        <div className="flex items-center gap-4 border-b-2 border-divider px-4 py-4 sm:px-8 lg:px-14">
            <Link href="/" className="me-auto flex items-center gap-3 no-underline lg:hidden">
                <StarMark size={34} />
                <span className="font-display text-xl font-extrabold">{t({ en: 'Sahl', ar: 'سهل' })}</span>
            </Link>
            <Link href="/" className="hidden items-center gap-2 text-[15px] font-semibold no-underline hover:text-accent lg:me-auto lg:flex">
                <span className="rotate-180">
                    <ArrowIcon size={18} />
                </span>
                {t({ en: 'Back to home', ar: 'العودة للرئيسية' })}
            </Link>
            <button
                type="button"
                onClick={toggleLocale}
                lang={t({ en: 'ar', ar: 'en' })}
                className="cursor-pointer border border-divider px-3.5 py-2 font-display text-[15px] font-extrabold hover:bg-ink/7"
            >
                {t({ en: 'العربية', ar: 'English' })}
            </button>
        </div>
    );
}

/* ─────────────────────────── Form ─────────────────────────── */

function SignInForm() {
    const { t } = useLocale();
    const page = usePage();
    const signInError = page.flash.signInError;
    const [audience, setAudience] = useState<Audience>(() =>
        new URLSearchParams(page.url.split('?')[1]).get('as') === 'staff' ? 'staff' : 'citizen',
    );

    return (
        <div className="w-full max-w-[480px]">
            <p className="m-0 mb-3 font-display text-sm font-extrabold tracking-[.12em] text-accent uppercase [&:lang(ar)]:tracking-normal">
                {t({ en: 'Sign in', ar: 'تسجيل الدخول' })}
            </p>
            <h1 className="m-0 mb-8 text-[40px] leading-[1.05] tracking-[-.03em] sm:text-5xl [&:lang(ar)]:leading-[1.3]">
                {t({ en: 'Welcome back.', ar: 'أهلاً بعودتك.' })}
            </h1>

            <div role="radiogroup" aria-label={t({ en: 'I am', ar: 'أنا' })} className="mb-8 grid grid-cols-2 border-2 border-ink">
                {AUDIENCES.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={audience === option.value}
                        onClick={() => setAudience(option.value)}
                        className="cursor-pointer px-3 py-3 text-[15px] font-semibold aria-checked:bg-ink aria-checked:text-paper not-aria-checked:hover:bg-ink/7"
                    >
                        {t(option.label)}
                    </button>
                ))}
            </div>

            {audience === 'citizen' ? <CitizenSignIn error={signInError} /> : <StaffSignIn error={signInError} />}
        </div>
    );
}

/* ─────────────────────────── Citizens (Entra External ID) ─────────────────────────── */

function CitizenSignIn({ error }: { error?: SignInError }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-6">
            <p className="m-0 text-[17px] leading-normal">
                {t({
                    en: 'Sign in or create your Sahl ID with your email. New here? You’ll be asked for your National ID once, when you sign up.',
                    ar: 'سجّل الدخول أو أنشئ هوية سهل باستخدام بريدك الإلكتروني. جديد هنا؟ سيُطلب منك رقم هويتك الوطنية مرة واحدة عند التسجيل.',
                })}
            </p>

            {error && <Notice>{t(SIGN_IN_ERRORS[error])}</Notice>}

            {/* A full page navigation: the browser leaves for the Sahl ID pages (Entra External ID). */}
            <a href="/auth/citizen/redirect" className={`${primaryButtonClass} no-underline`}>
                <span className="flex items-center gap-3">
                    <StarMark size={22} inverted />
                    {t({ en: 'Continue with Sahl ID', ar: 'المتابعة بهوية سهل' })}
                </span>
                <ArrowIcon />
            </a>

            <p className="m-0 border-t-2 border-divider pt-5 text-[14px] text-neutral-700">
                {t({
                    en: 'Demo: in this version the National ID you enter is not checked against a national register. A national digital ID will verify it in V2.',
                    ar: 'تجريبي: في هذا الإصدار لا يُتحقق من رقم الهوية الذي تدخله مقابل سجل وطني. ستتحقق منه الهوية الرقمية الوطنية في الإصدار الثاني.',
                })}
            </p>
        </div>
    );
}

/* ─────────────────────────── Staff (Entra ID) ─────────────────────────── */

const SIGN_IN_ERRORS: Record<SignInError, Bilingual> = {
    cancelled: {
        en: 'Sign-in was cancelled at Microsoft. Try again when you’re ready.',
        ar: 'أُلغي تسجيل الدخول لدى مايكروسوفت. حاول مجدداً عندما تكون جاهزاً.',
    },
    failed: {
        en: 'We couldn’t verify your sign-in with Microsoft. Please try again.',
        ar: 'تعذّر التحقق من تسجيل دخولك لدى مايكروسوفت. يرجى المحاولة مجدداً.',
    },
    no_role: {
        en: 'Your account isn’t assigned a reviewer role in Sahl. Ask your ministry’s administrator.',
        ar: 'حسابك غير مُسند إليه دور مراجع في سهل. تواصل مع مسؤول جهتك.',
    },
    no_national_id: {
        en: 'Your Sahl ID has no valid National ID (11 digits), so we can’t open your record. Contact support.',
        ar: 'لا تحمل هوية سهل الخاصة بك رقم هوية وطنية صالحاً (١١ رقماً)، لذا لا يمكننا فتح سجلك. تواصل مع الدعم.',
    },
    national_id_taken: {
        en: 'This National ID is already linked to another Sahl account. If it’s yours, contact support.',
        ar: 'رقم الهوية هذا مرتبط بحساب آخر في سهل. إن كان رقمك، فتواصل مع الدعم.',
    },
};

function StaffSignIn({ error }: { error?: SignInError }) {
    const { t } = useLocale();

    return (
        <div className="flex flex-col gap-6">
            <p className="m-0 text-[17px] leading-normal">
                {t({
                    en: 'Ministry staff sign in with their government work account. You’ll continue to Microsoft, then come back here.',
                    ar: 'يسجّل موظفو الجهات الدخول بحساب العمل الحكومي. ستنتقل إلى مايكروسوفت ثم تعود إلى هنا.',
                })}
            </p>

            {error && <Notice>{t(SIGN_IN_ERRORS[error])}</Notice>}

            {/* A full page navigation, not an Inertia visit: the browser has to leave for Microsoft. */}
            <a href="/auth/staff/redirect" className={`${primaryButtonClass} no-underline`}>
                <span className="flex items-center gap-3">
                    <MicrosoftMark />
                    {t({ en: 'Continue with Microsoft', ar: 'المتابعة عبر مايكروسوفت' })}
                </span>
                <ArrowIcon />
            </a>

            <p className="m-0 border-t-2 border-divider pt-5 text-[14px] text-neutral-700">
                {t({
                    en: 'Use the test reviewer you created in Entra ID. Sahl never sees your password; Microsoft checks it and your MFA.',
                    ar: 'استخدم حساب المراجع التجريبي الذي أنشأته في Entra ID. لا يرى سهل كلمة مرورك؛ تتحقق منها مايكروسوفت مع المصادقة متعددة العوامل.',
                })}
            </p>
        </div>
    );
}

/** Microsoft's four-square mark, drawn in paper so it sits on the red button. */
function MicrosoftMark() {
    return (
        <span aria-hidden="true" className="grid size-[18px] flex-none grid-cols-2 gap-[2px]">
            <span className="bg-paper" />
            <span className="bg-paper/80" />
            <span className="bg-paper/80" />
            <span className="bg-paper" />
        </span>
    );
}

/* ─────────────────────────── Shared bits ─────────────────────────── */

const primaryButtonClass =
    'flex cursor-pointer items-center justify-between gap-6 bg-accent px-5.5 py-4 font-display text-[17px] font-extrabold text-paper hover:bg-accent-600';

function Notice({ children }: { children: ReactNode }) {
    return (
        <p role="status" className="m-0 border-s-4 border-accent bg-accent-100 px-4 py-3 text-[15px]">
            {children}
        </p>
    );
}
