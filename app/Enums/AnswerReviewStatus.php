<?php

namespace App\Enums;

enum AnswerReviewStatus: string
{
    case Waiting = 'waiting';
    case Reviewed = 'reviewed';
}
