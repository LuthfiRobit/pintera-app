<?php

namespace App\Domains\Workflow\Enums;

enum ApprovalAction: string
{
    case Approve = 'APPROVE';
    case Reject = 'REJECT';
    case RequestRevision = 'REQUEST_REVISION';
    case Cancel = 'CANCEL';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Disetujui',
            self::Reject => 'Ditolak',
            self::RequestRevision => 'Minta Revisi',
            self::Cancel => 'Dibatalkan',
        };
    }
}
