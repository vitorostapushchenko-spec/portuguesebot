<?php

namespace App\Telegram\Handlers;

use App\Models\Lesson;
use App\Support\LessonAudioRecording;
use App\Support\Teacher;
use App\Support\TeacherMessages;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * The teacher records each phrase once, in the chat with the bot, and the recording then goes out with the lesson.
 *
 *   /audio        how many lessons have a recording, which are missing, and a button to record the next one
 *   /audio 12     record (or record again) lesson 12: the teacher's next voice message is saved for it
 *
 * After each recording the bot offers the next lesson without one, so the whole course is one tap per phrase.
 */
class LessonAudioHandler
{
    /** /audio */
    public function status(Nutgram $bot): void
    {
        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $total = Lesson::count();
        $missing = $this->missing();

        if ($missing === []) {
            $bot->sendMessage(TeacherMessages::audioAllRecorded($total));

            return;
        }

        $bot->sendMessage(
            TeacherMessages::audioStatus($total - count($missing), $total, $missing),
            reply_markup: $this->recordButton($missing[0]),
        );
    }

    /** /audio 12 */
    public function record(Nutgram $bot, ?string $number = null): void
    {
        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $number = trim((string) $number);

        if (! ctype_digit($number)) {
            $bot->sendMessage(TeacherMessages::audioUsage());

            return;
        }

        $this->ask($bot, (int) $number);
    }

    /** The "Record lesson N" button. */
    public function recordFromButton(Nutgram $bot, string $order): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $this->ask($bot, (int) $order);
    }

    /** The "Cancel" button under "send a voice message". */
    public function cancel(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        LessonAudioRecording::clear();
        $bot->sendMessage(TeacherMessages::audioCancelled());
    }

    /** Called by VoiceMessageHandler with the teacher's recording while a lesson is waiting for one. */
    public function save(Nutgram $bot, int $order, string $fileId, string $kind): void
    {
        LessonAudioRecording::clear();

        $lesson = Lesson::where('order', $order)->first();

        if ($lesson === null) {
            $bot->sendMessage(TeacherMessages::audioNoSuchLesson($order, Lesson::count()));

            return;
        }

        $lesson->update(['audio_file_id' => $fileId, 'audio_kind' => $kind]);

        $missing = $this->missing();
        // The next lesson without a recording after this one; from the start again when the rest is done.
        $next = collect($missing)->first(fn (int $n) => $n > $order) ?? ($missing[0] ?? null);

        $bot->sendMessage(
            TeacherMessages::audioSaved($lesson->order, $lesson->phrase_pt, count($missing)),
            reply_markup: $next === null ? null : $this->recordButton($next),
        );
    }

    private function ask(Nutgram $bot, int $order): void
    {
        $lesson = Lesson::where('order', $order)->first();

        if ($lesson === null) {
            $bot->sendMessage(TeacherMessages::audioNoSuchLesson($order, Lesson::count()));

            return;
        }

        // The recording there is now, so the teacher can hear it before deciding to replace it.
        if ($lesson->hasAudio()) {
            try {
                $lesson->audio_kind === 'audio'
                    ? $bot->sendAudio($lesson->audio_file_id)
                    : $bot->sendVoice($lesson->audio_file_id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        LessonAudioRecording::start($lesson->order);

        $bot->sendMessage(
            TeacherMessages::audioAskRecording($lesson->order, $lesson->phrase_pt, $lesson->hasAudio()),
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('Cancel', callback_data: 'audio:cancel'),
            ),
        );
    }

    /** @return list<int> the numbers of the lessons that have no recording yet, in order */
    private function missing(): array
    {
        return Lesson::query()
            ->where(fn ($query) => $query->whereNull('audio_file_id')->orWhere('audio_file_id', ''))
            ->orderBy('order')
            ->pluck('order')
            ->map(fn ($order) => (int) $order)
            ->all();
    }

    private function recordButton(int $order): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make("🎙 Record lesson {$order}", callback_data: "audio:rec:{$order}"),
        );
    }
}
