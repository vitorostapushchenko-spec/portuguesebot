<?php

namespace App\Services;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Support\BotMessages;
use App\Support\MainKeyboard;
use Carbon\CarbonImmutable;
use SergiX44\Nutgram\Nutgram;

/**
 * Shared by the onboarding time question (§3.1) and /time (§3.9): both use
 * the same buttons/typed formats and the same confirmation message.
 */
class TimeSetter
{
    public function apply(Learner $learner, int $hour, int $minute, Nutgram $bot): void
    {
        $isFirstTimeEver = $learner->status === LearnerStatus::SettingUp;

        $learner->update([
            'lesson_time' => sprintf('%02d:%02d:00', $hour, $minute),
            'status' => $isFirstTimeEver ? LearnerStatus::Active : $learner->status,
        ]);

        $bot->sendMessage(
            BotMessages::timeConfirmed(TimeParser::format($hour, $minute)),
            reply_markup: MainKeyboard::attachOnceFor($learner),
        );

        // First time ever: don't make the learner wait for the clock to reach
        // the chosen hour — send lesson 1 right now. Daily lessons from then
        // on follow the chosen time as usual (the "already sent today" guard
        // in LessonScheduler prevents this from also firing a second time at
        // the originally-chosen hour later today).
        if ($isFirstTimeEver) {
            (new LessonScheduler($bot))->scheduledTick($learner, CarbonImmutable::now(config('course.timezone')));
        }
    }
}
