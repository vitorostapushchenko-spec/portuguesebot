<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Scheduled = 'scheduled';
    case Repeated = 'repeated';
    case Next = 'next';
}
