<?php

namespace App\Enums;

enum CertificatePurpose: string
{
    case Employment = 'employment';
    case Travel = 'travel';
    case Residency = 'residency';
    case Education = 'education';
    case Other = 'other';

    /**
     * How the purpose reads on the certificate. Same wording as the website (`PURPOSES` in application-bits.tsx).
     *
     * @return array{ar: string, en: string}
     */
    public function label(): array
    {
        return match ($this) {
            self::Employment => ['ar' => 'عمل', 'en' => 'Employment'],
            self::Travel => ['ar' => 'سفر', 'en' => 'Travel'],
            self::Residency => ['ar' => 'إقامة', 'en' => 'Residency'],
            self::Education => ['ar' => 'دراسة', 'en' => 'Education'],
            self::Other => ['ar' => 'أخرى', 'en' => 'Other'],
        };
    }
}
