import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Locale = 'ar' | 'en';

/** A string in both languages. Same shape the API returns for names and descriptions. */
export type Bilingual = { ar: string; en: string };

type LocaleContextValue = {
    locale: Locale;
    dir: 'rtl' | 'ltr';
    toggleLocale: () => void;
    /** Pick the current language's half of a bilingual string. */
    t: (text: Bilingual) => string;
    /** Format a number with the current language's digits (Arabic-Indic in Arabic). */
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
};

const LocaleContext = createContext<LocaleContextValue | null>(null);

const COOKIE = 'lang';
const ONE_YEAR = 60 * 60 * 24 * 365;

export function LocaleProvider({ initialLocale, children }: { initialLocale: Locale; children: ReactNode }) {
    const [locale, setLocale] = useState<Locale>(initialLocale);
    const dir = locale === 'ar' ? 'rtl' : 'ltr';

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
        document.cookie = `${COOKIE}=${locale}; path=/; max-age=${ONE_YEAR}; samesite=lax`;
    }, [locale, dir]);

    const toggleLocale = useCallback(() => setLocale((current) => (current === 'ar' ? 'en' : 'ar')), []);

    const value = useMemo<LocaleContextValue>(
        () => ({
            locale,
            dir,
            toggleLocale,
            t: (text) => text[locale],
            formatNumber: (number, options) => new Intl.NumberFormat(locale === 'ar' ? 'ar-QA' : 'en-QA', options).format(number),
        }),
        [locale, dir, toggleLocale],
    );

    return <LocaleContext value={value}>{children}</LocaleContext>;
}

export function useLocale(): LocaleContextValue {
    const context = useContext(LocaleContext);

    if (!context) {
        throw new Error('useLocale must be used inside <LocaleProvider>.');
    }

    return context;
}
