<?php

namespace App\Services;

use App\Enums\LearnerStatus;
use App\Enums\ReviewVerdict;
use App\Models\Answer;
use App\Models\Review;
use App\Support\BotMessages;
use App\Support\LessonAudioRecording;
use App\Support\TeacherMessages;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * The teacher-side review flow (spec §3.4).
 *
 * Good is sent at once: one tap and the learner gets "Молодец!". Not yet takes more steps, because the teacher
 * records the phrase the right way: verdict -> record the tip or tap Skip -> confirm (Send / Start over).
 */
class ReviewFlow
{
    /** Not yet: asks the teacher for the spoken tip. (Good never gets here: it is sent without asking for anything.) */
    public function askForRecording(Nutgram $bot, Review $review): void
    {
        // From here on the teacher's next voice message is this tip, not a lesson recording started with /audio.
        LessonAudioRecording::clear();

        $sent = $bot->sendMessage(
            TeacherMessages::askForTip(),
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('Skip', callback_data: "rev:skip:{$review->id}")
            ),
        );

        $review->update(['prompt_message_id' => $sent?->message_id]);
    }

    public function confirmStep(Nutgram $bot, Review $review): void
    {
        $answer = $review->answer;
        $learner = $answer->learner;
        $verdictLabel = $review->verdict === ReviewVerdict::Good ? '✅ Good' : '❌ Not yet';

        $bot->sendMessage(
            TeacherMessages::confirmSend($learner->displayName(), $verdictLabel, $review->teacher_file_id !== null),
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('Send', callback_data: "rev:send:{$review->id}"),
                InlineKeyboardButton::make('Start over', callback_data: "rev:redo:{$review->id}"),
            ),
        );
    }

    /** True when this answer already got a Good that went out, so tapping Good again must not praise twice. */
    public function alreadySentGood(Answer $answer): bool
    {
        return Review::where('answer_id', $answer->id)
            ->where('verdict', ReviewVerdict::Good)
            ->whereNotNull('sent_at')
            ->exists();
    }

    /**
     * The teacher tapped Good: the learner gets the praise right away. No recording is asked for and there is no
     * confirmation step. A recording left over from a Not yet that was started and abandoned is dropped, so the
     * learner never gets "Молодец!" together with a correction.
     */
    public function sendGood(Nutgram $bot, Answer $answer): void
    {
        $review = Review::firstOrCreate(
            ['answer_id' => $answer->id, 'sent_at' => null],
            ['verdict' => ReviewVerdict::Good],
        );

        $review->update(['verdict' => ReviewVerdict::Good, 'teacher_file_id' => null, 'prompt_message_id' => null]);

        $this->deliver($bot, $review);
    }

    /**
     * Sends a review to the learner as a reply to their voice message: the verdict and, if the teacher recorded
     * one, the voice message. Then it tells the teacher. A learner who has blocked the bot is marked as blocked.
     */
    public function deliver(Nutgram $bot, Review $review): void
    {
        $answer = $review->answer;
        $learner = $answer->learner;
        $lesson = $answer->lessonSend->lesson;

        $verdictText = $review->verdict === ReviewVerdict::Good
            ? BotMessages::verdictGood()
            : BotMessages::verdictNotYet();

        $text = BotMessages::feedbackHeader($lesson->order)."\n{$lesson->phrase_pt}\n{$verdictText}";

        try {
            $bot->sendMessage($text, chat_id: $learner->telegram_id, reply_to_message_id: $answer->telegram_message_id);

            if ($review->teacher_file_id) {
                $bot->sendVoice($review->teacher_file_id, chat_id: $learner->telegram_id, reply_to_message_id: $answer->telegram_message_id);
            }

            $review->update(['sent_at' => now()]);
            $answer->update(['review_status' => 'reviewed']);

            $bot->sendMessage(TeacherMessages::sentConfirmation($learner->displayName()));
        } catch (\Throwable $e) {
            report($e);
            $bot->sendMessage(TeacherMessages::learnerBlocked($learner->displayName()));
            $learner->update(['status' => LearnerStatus::Blocked]);
        }
    }
}
