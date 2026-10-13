<?php

namespace App\Enums;

enum SendStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Missed = 'missed';
}
