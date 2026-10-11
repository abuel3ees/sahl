import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StarMark } from '@/components/site/star-mark';
import { NAV_LINKS } from '@/components/site/nav-links';
import { useLocale } from '@/lib/i18n';

type Tone = 'red' | 'light';

/**
 * Official banner + main navigation. "red" sits on the landing hero, "light" on inner pages.
 */
export function SiteHeader({ tone, currentPath }: { tone: Tone; currentPath?: string }) {
    const { t, locale, toggleLocale } = useLocale();
    const [menuOpen, setMenuOpen] = useState(false);
    const user = usePage().props.auth.user;
    const onRed = tone === 'red';

    return (
        <header className={`relative z-20 ${onRed ? 'text-paper' : 'text-ink'}`}>
            <div
                className={`flex items-center gap-2.5 px-4 py-2 text-[13px] sm:px-8 lg:px-14 ${onRed ? 'bg-accent-700' : 'bg-ink text-paper'}`}
            >
                <span aria-hidden="true" className={`size-2.5 flex-none ${onRed ? 'bg-paper' : 'bg-accent'}`} />
                {t({
                    en: 'An official website of the State of Al-Wahah — fictional demonstration',
                    ar: 'موقع رسمي لدولة الواحة — نموذج تجريبي لأغراض العرض',
                })}
            </div>

            <nav
                aria-label={t({ en: 'Main', ar: 'الرئيسية' })}
                className={`flex items-center gap-4 border-b-2 px-4 py-4 sm:px-8 lg:gap-9 lg:px-14 lg:py-5 ${onRed ? 'border-paper/35' : 'border-divider'}`}
            >
                <Link href="/" className="me-auto flex items-center gap-3.5 no-underline">
                    <StarMark inverted={onRed} />
                    <span className="flex flex-col leading-[1.05]">
                        <span className="font-display text-[22px] font-extrabold">{t({ en: 'Sahl', ar: 'سهل' })}</span>
                        {!onRed && (
                            <span className="text-xs whitespace-nowrap text-neutral-700">
                                {t({ en: 'Unified Government Services', ar: 'الخدمات الحكومية الموحّدة' })}
                            </span>
                        )}
                    </span>
                </Link>

                <div className="hidden items-center gap-8 xl:flex">
                    {NAV_LINKS.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            aria-current={currentPath === link.href ? 'page' : undefined}
                            className={`text-[15px] font-semibold whitespace-nowrap no-underline ${onRed ? 'hover:text-paper/75' : 'hover:text-accent aria-[current=page]:text-accent'}`}
                        >
                            {t(link.label)}
                        </Link>
                    ))}
                </div>

                <button
                    type="button"
                    onClick={toggleLocale}
                    lang={t({ en: 'ar', ar: 'en' })}
                    className={`cursor-pointer border px-3.5 py-2 font-display text-[15px] font-extrabold ${onRed ? 'border-paper/50 hover:bg-paper/10' : 'border-divider hover:bg-ink/7'}`}
                >
                    {t({ en: 'العربية', ar: 'English' })}
                </button>
                <Link
                    href={user ? (user.type === 'employee' ? '/staff' : '/account') : '/login'}
                    className={`hidden px-4.5 py-2.5 font-display text-[15px] font-extrabold whitespace-nowrap no-underline sm:inline-flex ${onRed ? 'bg-paper text-accent-700 hover:bg-accent-100' : 'bg-accent text-paper hover:bg-accent-600'}`}
                >
                    {user ? (locale === 'ar' && user.name_ar ? user.name_ar : user.name) : t({ en: 'Sign in', ar: 'تسجيل الدخول' })}
                </Link>

                <button
                    type="button"
                    onClick={() => setMenuOpen((open) => !open)}
                    aria-expanded={menuOpen}
                    aria-controls="mobile-menu"
                    aria-label={t({ en: 'Menu', ar: 'القائمة' })}
                    className="grid size-10 cursor-pointer place-items-center xl:hidden"
                >
                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="square">
                        {menuOpen ? <path d="M6 6l12 12M18 6 6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
                    </svg>
                </button>
            </nav>

            {menuOpen && (
                <div
                    id="mobile-menu"
                    className={`flex flex-col border-b-2 px-4 py-2 sm:px-8 xl:hidden ${onRed ? 'border-paper/35' : 'border-divider bg-paper'}`}
                >
                    {NAV_LINKS.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            onClick={() => setMenuOpen(false)}
                            className="border-b border-current/20 py-3 text-base font-semibold no-underline last:border-0"
                        >
                            {t(link.label)}
                        </Link>
                    ))}
                </div>
            )}
        </header>
    );
}
