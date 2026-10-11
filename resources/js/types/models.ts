import type { Bilingual } from '@/lib/i18n';

/** Mirrors App\Http\Resources\Api\V1\GovEntityResource. */
export type GovEntity = {
    code: string;
    name: Bilingual;
};

/** Mirrors App\Http\Resources\Api\V1\ServiceResource. */
export type Service = {
    code: string;
    slug: string;
    name: Bilingual;
    description: Bilingual;
    fee: { amount_minor: number; currency: string };
    sla_working_days: number;
    validity_days: number | null;
    gov_entity?: GovEntity;
};

export type ApplicationStatus = 'submitted' | 'approved' | 'rejected';

export type CertificatePurpose = 'employment' | 'travel' | 'residency' | 'education' | 'other';

/** Mirrors App\Http\Resources\Api\V1\UserResource. */
export type Person = {
    id: number;
    type: 'citizen' | 'employee';
    name: Bilingual;
    email: string;
    national_id: string | null;
};

/** Mirrors App\Http\Resources\Api\V1\ApplicationStatusChangeResource. */
export type TimelineEntry = {
    from_status: ApplicationStatus | null;
    to_status: ApplicationStatus;
    actor: 'applicant' | 'ministry';
    note: string | null;
    at: string;
};

/** Mirrors App\Http\Resources\Api\V1\CertificateResource. */
export type Certificate = {
    serial_number: string;
    verification_code: string;
    verification_url: string;
    issued_at: string;
    expires_at: string | null;
    is_expired: boolean;
    content: { holder_name_ar: string; holder_name_en: string; holder_national_id: string; language: 'ar' | 'en' };
};

/** Mirrors App\Http\Resources\Api\V1\ApplicationResource. */
export type Application = {
    id: string;
    reference_number: string;
    status: ApplicationStatus;
    service?: Service;
    applicant?: Person;
    purpose: CertificatePurpose;
    addressed_to: string | null;
    certificate_language: 'ar' | 'en';
    submitted_at: string;
    decided_at: string | null;
    decision_note: string | null;
    sla?: { working_days: number; working_days_waiting: number; is_overdue: boolean };
    timeline?: TimelineEntry[];
    certificate?: Certificate | null;
};

/** A Laravel paginated resource collection, as the API returns it. */
export type Paginated<T> = {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
};

/** Mirrors App\Http\Resources\Api\V1\CertificateVerificationResource: public, masked. */
export type CertificateVerification = {
    status: 'valid' | 'expired';
    serial_number: string;
    service: Bilingual;
    issuer: Bilingual;
    holder_name: Bilingual;
    holder_national_id_masked: string;
    issued_at: string;
    expires_at: string | null;
};
