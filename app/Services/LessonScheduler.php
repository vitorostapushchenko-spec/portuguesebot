<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\LearnerStatus;
use App\Enums\SendStatus;
use App\Models\Learner;
use App\Models\Lesson;
use App\Models\LessonSend;
use App\Support\BotMessages;
use App\Support\MainKeyboard;
use Carbon\CarbonImmutable;
use SergiX44\Nutgram\Nutgram;

/**
 * The single place that decides what happens next for a learner: repeat the
 * current lesson, advance to the next one, pause for inactivity, or finish
 * the course. Used both by the per-minute scheduled dispatch and by /next,
 * so both paths follow the exact same rules (spec §3.2, §3.6, §3.7, §3.8).
 */
class LessonScheduler
{
    public function __construct(private Nutgram $bot) {}

    /**
     * Called once per minute for every learner whose chosen time is now.
     */
    public function scheduledTick(Learner $learner, CarbonImmutable $now): void
    {
        if ($learner->status !== LearnerStatus::Active) {
            return;
        }

        // The scheduled lesson never arrives twice on the same day (§3.9), which also
        // covers a same-day /time change: if today's slot already fired, the new time
        // only takes effect tomorrow, even though it now matches "now" again this minute.
        $alreadySentToday = $learner->sends()
            ->whereIn('delivery_type', [DeliveryType::Scheduled, DeliveryType::Repeated])
            ->whereDate('sent_at', $now->toDateString())
            ->exists();

        if ($alreadySentToday) {
            return;
        }

        $current = $learner->currentSend();

        if ($current === null) {
            $this->sendLesson($learner, $learner->current_lesson_number, DeliveryType::Scheduled, isRepeat: false, now: $now);

            return;
        }

        if ($current->status === SendStatus::Open) {
            $this->handleUnanswered($learner, $current, $now);

            return;
        }

        $this->advance($learner, DeliveryType::Scheduled, openingOverride: null, now: $now);
    }

    /**
     * /next: only allowed once the current lesson has been answered (§3.6).
     */
    public function sendNext(Learner $learner, CarbonImmutable $now): string
    {
        if ($learner->status === LearnerStatus::Finished) {
            return 'no_lessons_left';
        }

        $current = $learner->currentSend();

        // No lesson sent yet at all (e.g. before the learner's first scheduled time):
        // /next has nothing to "get more of" yet, same as an unanswered current lesson.
        if ($current === null || $current->status === SendStatus::Open) {
            return 'refused';
        }

        if ($learner->current_lesson_number >= config('course.total_lessons')) {
            return 'no_lessons_left';
        }

        $this->advance($learner, DeliveryType::Next, openingOverride: BotMessages::nextOpening(), now: $now);

        return 'sent';
    }

    private function handleUnanswered(Learner $learner, LessonSend $current, CarbonImmutable $now): void
    {
        $current->update(['status' => SendStatus::Missed]);
        $learner->increment('consecutive_unanswered');
        $learner->refresh();

        if ($learner->consecutive_unanswered >= config('course.pause_after_days')) {
            $learner->update(['status' => LearnerStatus::PausedInactive]);
            $this->bot->sendMessage(BotMessages::pausedForInactivity(), chat_id: $learner->telegram_id);

            return;
        }

        $this->sendLesson($learner, $learner->current_lesson_number, DeliveryType::Repeated, isRepeat: true, now: $now);
    }

    private function advance(Learner $learner, DeliveryType $type, ?string $openingOverride, CarbonImmutable $now): void
    {
        $learner->update(['consecutive_unanswered' => 0]);

        if ($learner->current_lesson_number >= config('course.total_lessons')) {
            $learner->update(['status' => LearnerStatus::Finished]);
            $this->bot->sendMessage(BotMessages::courseFinished(), chat_id: $learner->telegram_id);

            return;
        }

        $learner->increment('current_lesson_number');
        $learner->refresh();

        $this->sendLesson($learner, $learner->current_lesson_number, $type, isRepeat: false, now: $now, openingOverride: $openingOverride);
    }

    private function sendLesson(Learner $learner, int $lessonNumber, DeliveryType $type, bool $isRepeat, CarbonImmutable $now, ?string $openingOverride = null): void
    {
        $lesson = Lesson::where('order', $lessonNumber)->firstOrFail();

        $opening = $openingOverride ?? BotMessages::dailyLessonGreeting($now, $isRepeat);
        $this->bot->sendMessage($opening, chat_id: $learner->telegram_id, reply_markup: MainKeyboard::attachOnceFor($learner));

        app(LessonDelivery::class)->send($this->bot, $lesson, $learner->telegram_id);

        LessonSend::create([
            'learner_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'delivery_type' => $type,
            'status' => SendStatus::Open,
            'sent_at' => $now,
        ]);

        if ($learner->course_started_at === null) {
            $learner->update(['course_started_at' => $now]);
        }
    }
}
