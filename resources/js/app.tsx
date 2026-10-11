import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { LocaleProvider } from '@/lib/i18n';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · Sahl` : 'Sahl · سهل'),
    resolve: async (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return (await page()).default;
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(
            <LocaleProvider initialLocale={props.initialPage.props.locale}>
                <App {...props} />
            </LocaleProvider>,
        );
    },
    progress: { color: '#ec3013' },
});
