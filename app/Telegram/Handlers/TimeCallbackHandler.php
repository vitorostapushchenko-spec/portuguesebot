<?php

namespace App\Telegram\Handlers;

use App\Models\Learner;
use App\Services\TimeParser;
use App\Services\TimeSetter;
use SergiX44\Nutgram\Nutgram;

/**
 * Handles taps on the time-picker buttons (callback_data "time:HH:MM"),
 * used by both onboarding (§3.1) and /time (§3.9).
 */
class TimeCallbackHandler
{
    public function handle(Nutgram $bot, string $time): void
    {
        $bot->answerCallbackQuery();

        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null) {
            return;
        }

        $parsed = TimeParser::parse($time);

        if ($parsed === null) {
            return;
        }

        app(TimeSetter::class)->apply($learner, $parsed['hour'], $parsed['minute'], $bot);
    }
}
