import type { Bilingual } from '@/lib/i18n';

export type NavLink = { href: string; label: Bilingual };

export const NAV_LINKS: NavLink[] = [
    { href: '/#featured-service', label: { en: 'Services', ar: 'الخدمات' } },
    { href: '/#track', label: { en: 'Track a request', ar: 'متابعة طلب' } },
    { href: '/verify', label: { en: 'Verify a certificate', ar: 'التحقق من شهادة' } },
    { href: '/docs/ministry-of-interior', label: { en: 'Documentation', ar: 'التوثيق' } },
    { href: '/developers', label: { en: 'Developers', ar: 'المطوّرون' } },
];
