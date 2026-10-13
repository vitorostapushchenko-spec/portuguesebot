<?php

namespace App\Enums;

enum LearnerStatus: string
{
    case SettingUp = 'setting_up';
    case Active = 'active';
    case Paused = 'paused';
    case PausedInactive = 'paused_inactive';
    case Finished = 'finished';
    case Blocked = 'blocked';
}
