<?php

namespace App\Enums;

enum EventCandidateStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
}
