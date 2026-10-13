<?php

namespace App\Telegram\Handlers;

use App\Enums\LearnerStatus;
use App\Enums\SendStatus;
use App\Models\Answer;
use App\Models\Learner;
use App\Models\LessonSend;
use App\Models\Review;
use App\Services\LevelBarRenderer;
use App\Services\ProgressCalculator;
use App\Services\ReviewFlow;
use App\Support\BotMessages;
use App\Support\LessonAudioRecording;
use App\Support\Teacher;
use App\Support\TeacherMessages;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Voice (and audio-file) messages, from either side: a learner's answer
 * (§3.3), the teacher's tip/correction recording (§3.4), or the teacher's
 * recording of a lesson's phrase (/audio).
 */
class VoiceMessageHandler
{
    public function handle(Nutgram $bot): void
    {
        if (Teacher::isTeacher($bot)) {
            $this->handleTeacherRecording($bot);

            return;
        }

        $this->handleLearnerAnswer($bot);
    }

    private function fileId(Nutgram $bot): ?string
    {
        return $bot->message()?->voice?->file_id ?? $bot->message()?->audio?->file_id;
    }

    private function handleLearnerAnswer(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();
        $fileId = $this->fileId($bot);

        if ($learner === null || $fileId === null) {
            return;
        }

        $nextTime = $learner->lesson_time ? substr((string) $learner->lesson_time, 0, 5) : null;
        $current = $learner->status === LearnerStatus::Active ? $learner->currentSend() : null;

        if ($current === null) {
            $bot->sendMessage(BotMessages::noLessonToAnswer($nextTime));

            return;
        }

        $isFirstAttempt = $current->status === SendStatus::Open;

        if ($isFirstAttempt) {
            $current->update(['status' => SendStatus::Answered]);
        }

        $answer = Answer::create([
            'learner_id' => $learner->id,
            'lesson_send_id' => $current->id,
            'attempt_number' => $current->answers()->count() + 1,
            'telegram_file_id' => $fileId,
            'telegram_message_id' => $bot->messageId(),
            'received_at' => now(),
            'review_status' => 'waiting',
        ]);

        $level = app(ProgressCalculator::class)->level($learner);

        $this->forwardToTeacher($bot, $learner, $current, $answer, $level);

        if ($isFirstAttempt) {
            ['path' => $barPath] = app(LevelBarRenderer::class)->renderForLearner($learner);

            $bot->sendPhoto(
                InputFile::make($barPath),
                caption: BotMessages::answerReceived($level, BotMessages::levelMessage($level), $nextTime ?? '—'),
            );

            @unlink($barPath);
        } else {
            $bot->sendMessage(BotMessages::anotherAttemptReceived());
        }
    }

    private function forwardToTeacher(Nutgram $bot, Learner $learner, LessonSend $current, Answer $answer, int $level): void
    {
        $teacherChatId = config('course.teacher_chat_id');

        if (! $teacherChatId) {
            return;
        }

        $lesson = $current->lesson;

        $bot->sendMessage(
            TeacherMessages::newAnswerToReview(
                $learner->displayName(),
                $learner->username,
                $lesson->order,
                config('course.total_lessons'),
                $lesson->phrase_pt,
                $answer->attempt_number,
                $level,
            ),
            chat_id: $teacherChatId,
        );

        $bot->sendVoice(
            $answer->telegram_file_id,
            chat_id: $teacherChatId,
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✅ Good', callback_data: "rev:good:{$answer->id}"),
                InlineKeyboardButton::make('❌ Not yet', callback_data: "rev:notyet:{$answer->id}"),
            ),
        );
    }

    private function handleTeacherRecording(Nutgram $bot): void
    {
        $fileId = $this->fileId($bot);

        if ($fileId === null) {
            return;
        }

        $replyToId = $bot->message()?->reply_to_message?->message_id;

        $review = $replyToId
            ? Review::whereNull('sent_at')->where('prompt_message_id', $replyToId)->first()
            : null;

        // A recording for a lesson (/audio), unless this voice message is a reply to a review's "record your tip".
        if ($review === null && ($lessonNumber = LessonAudioRecording::pending()) !== null) {
            $kind = $bot->message()?->voice !== null ? 'voice' : 'audio';
            app(LessonAudioHandler::class)->save($bot, $lessonNumber, $fileId, $kind);

            return;
        }

        $review ??= Review::whereNull('sent_at')->whereNotNull('prompt_message_id')->latest('id')->first();

        if ($review === null) {
            return;
        }

        $review->update(['teacher_file_id' => $fileId]);

        app(ReviewFlow::class)->confirmStep($bot, $review);
    }
}
