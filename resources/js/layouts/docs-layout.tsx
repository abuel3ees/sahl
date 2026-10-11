import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { useLocale, type Bilingual } from '@/lib/i18n';

type Props = {
    currentPath: string;
    kicker: Bilingual;
    title: ReactNode;
    intro?: ReactNode;
    sidebar: ReactNode;
    children: ReactNode;
};

/**
 * Inner-page shell for documentation: light header, a title band, a sticky sidebar and an
 * English-only content column (the docs are written in English; the chrome follows the toggle).
 */
export function DocsLayout({ currentPath, kicker, title, intro, sidebar, children }: Props) {
    const { t, locale } = useLocale();

    return (
        <>
            <SiteHeader tone="light" currentPath={currentPath} />

            <div className="flex flex-col gap-5 border-b-2 border-divider px-4 py-12 sm:px-8 lg:px-14 lg:py-16">
                <span className="flex items-center gap-3 text-[13px] font-semibold tracking-[.14em] text-accent-700 uppercase">
                    <span aria-hidden="true" className="size-3 bg-accent" />
                    {t(kicker)}
                </span>
                <div dir="ltr" lang="en" className="flex flex-col gap-5 text-start">
                    <h1 className="m-0 max-w-[1100px] text-[44px] leading-none tracking-[-.035em] text-balance sm:text-6xl lg:text-[72px]">
                        {title}
                    </h1>
                    {intro}
                </div>
                {locale === 'ar' && (
                    <p className="m-0 max-w-[720px] border-s-[6px] border-accent bg-surface px-4 py-3 text-[15px]">
                        هذه الوثائق التقنية متوفرة باللغة الإنجليزية فقط حالياً.
                    </p>
                )}
            </div>

            <div className="grid lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside className="hidden border-e-2 border-divider lg:block">
                    <div className="sticky top-0 max-h-screen overflow-y-auto px-8 py-10">{sidebar}</div>
                </aside>
                <main dir="ltr" lang="en" className="min-w-0 px-4 py-12 text-start sm:px-8 lg:px-14">
                    {children}
                </main>
            </div>

            <SiteFooter />
        </>
    );
}
