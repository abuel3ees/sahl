import type { Locale } from '@/lib/i18n';

export type SignInError = 'cancelled' | 'failed' | 'no_role' | 'no_national_id' | 'national_id_taken';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            locale: Locale;
            auth: { user: { name: string; name_ar: string | null; type: 'citizen' | 'employee' } | null };
        };
        flashDataType: {
            signInError?: SignInError;
            decision?: 'approved' | 'rejected' | 'already_decided';
            submitted?: boolean;
        };
    }
}
