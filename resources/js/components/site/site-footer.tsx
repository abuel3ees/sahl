import { Link } from '@inertiajs/react';
import { useLocale } from '@/lib/i18n';

export function SiteFooter() {
    const { t } = useLocale();

    return (
        <footer className="flex flex-wrap items-center gap-x-8 gap-y-4 bg-ink px-4 py-8 text-sm text-paper sm:px-8 lg:px-14">
            <span lang="ar" className="font-kufi text-[32px] leading-none text-accent-500">
                سهل
            </span>
            <span className="me-auto">
                {t({
                    en: '© 2026 State of Al-Wahah. All entities and data are fictional.',
                    ar: '© ٢٠٢٦ دولة الواحة. جميع الجهات والبيانات افتراضية.',
                })}
            </span>
            <Link href="/developers" className="text-paper no-underline hover:text-accent-400">
                {t({ en: 'Developers', ar: 'المطوّرون' })}
            </Link>
            <Link href="/docs/ministry-of-interior" className="text-paper no-underline hover:text-accent-400">
                {t({ en: 'Documentation', ar: 'التوثيق' })}
            </Link>
            <span>{t({ en: 'Accessibility', ar: 'إمكانية الوصول' })}</span>
            <span>{t({ en: 'Privacy', ar: 'الخصوصية' })}</span>
        </footer>
    );
}
