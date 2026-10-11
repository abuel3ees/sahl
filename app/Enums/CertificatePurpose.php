<?php

namespace App\Enums;

enum CertificatePurpose: string
{
    case Employment = 'employment';
    case Travel = 'travel';
    case Residency = 'residency';
    case Education = 'education';
    case Other = 'other';
}
