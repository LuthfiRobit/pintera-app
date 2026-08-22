<?php

namespace App\Domains\Workflow\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequired = 'revision_required';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Review',
            self::InReview => 'Sedang Direview',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::RevisionRequired => 'Perlu Revisi',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InReview => 'blue',
            self::Approved => 'green',
            self::Rejected => 'rose',
            self::RevisionRequired => 'amber',
            self::Cancelled => 'slate',
        };
    }
}
