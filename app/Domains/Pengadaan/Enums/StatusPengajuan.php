<?php

namespace App\Domains\Pengadaan\Enums;

enum StatusPengajuan: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disbursed = 'disbursed';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft Usulan',
            self::Submitted => 'Diajukan',
            self::InReview => 'Sedang Direview',
            self::RevisionRequired => 'Perlu Revisi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Disbursed => 'Dana Cair',
            self::Completed => 'Selesai (LPJ Selesai)',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'blue',
            self::InReview => 'purple',
            self::RevisionRequired => 'amber',
            self::Approved => 'green',
            self::Rejected => 'rose',
            self::Disbursed => 'indigo',
            self::Completed => 'green',
        };
    }
}
