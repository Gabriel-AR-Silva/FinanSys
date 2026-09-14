<?php

namespace App\Enums;

enum OfxReviewStatus: string
{
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
