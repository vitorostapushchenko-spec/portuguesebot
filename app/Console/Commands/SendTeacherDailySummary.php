<?php

namespace App\Console\Commands;

use App\Enums\LearnerStatus;
use App\Models\Answer;
use App\Models\Learner;
use App\Support\TeacherMessages;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Daily 09:00 summary to the teacher (spec §3.4): sent only if there's
 * something to report (pending reviews and/or newly-paused learners).
 */
class SendTeacherDailySummary extends Command
{
    protected $signature = 'teacher:daily-summary';

    protected $description = "Tell the teacher how many answers are waiting for review, if any.";

    public function handle(Nutgram $bot): void
    {
        $teacherChatId = config('course.teacher_chat_id');

        if (! $teacherChatId) {
            return;
        }

        $pendingCount = Answer::where('review_status', 'waiting')->count();

        $newlyPaused = Learner::where('status', LearnerStatus::PausedInactive)
            ->whereNull('inactivity_notified_at')
            ->get();

        if ($pendingCount === 0 && $newlyPaused->isEmpty()) {
            return;
        }

        $names = $newlyPaused->map(fn (Learner $learner) => $learner->displayName())->all();

        $keyboard = $pendingCount > 0
            ? InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('📋 Open pending', callback_data: 'pending:list')
            )
            : null;

        $bot->sendMessage(
            TeacherMessages::dailySummary($pendingCount, $names),
            chat_id: $teacherChatId,
            reply_markup: $keyboard,
        );

        $newlyPaused->each(fn (Learner $learner) => $learner->update(['inactivity_notified_at' => now()]));
    }
}
