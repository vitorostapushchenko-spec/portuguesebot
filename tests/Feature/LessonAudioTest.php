<?php

namespace Tests\Feature;

use App\Enums\DeliveryType;
use App\Enums\LearnerStatus;
use App\Enums\ReviewVerdict;
use App\Enums\SendStatus;
use App\Models\Answer;
use App\Models\Learner;
use App\Models\Lesson;
use App\Models\LessonSend;
use App\Models\Review;
use App\Services\LessonScheduler;
use App\Support\BotMessages;
use App\Support\LessonAudioRecording;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

/**
 * The teacher's recording of each phrase: recorded once in the chat with the bot (/audio), then sent to every
 * learner right after the lesson, so they hear how the phrase sounds before answering.
 */
class LessonAudioTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER = 777000111;

    private const LEARNER = 4242;

    private FakeNutgram $bot;

    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['course.teacher_chat_id' => self::TEACHER, 'course.total_lessons' => 3]);

        foreach ([1 => 'Oi, tudo bem?', 2 => 'Tudo bem, e você?', 3 => 'Prazer em conhecer!'] as $order => $phrase) {
            Lesson::create([
                'order' => $order,
                'category' => 'Test',
                'phrase_pt' => $phrase,
                'phrase_ru' => "Фраза {$order}",
                'phrase_en' => "Phrase {$order}",
                'pronunciation' => "Фраза {$order}",
                'has_variant' => false,
            ]);
        }

        $this->learner = Learner::create([
            'telegram_id' => self::LEARNER,
            'first_name' => 'Anna',
            'lesson_time' => '19:00:00',
            'status' => LearnerStatus::Active,
        ]);

        $this->bot = Nutgram::fake();

        // The real handlers, so the tests also cover the routing of "/audio", "/audio 12" and the buttons.
        (function (Nutgram $bot) {
            require base_path('routes/telegram.php');
        })($this->bot);
    }

    // --- helpers --------------------------------------------------------------------------------------------

    private function as(int $chatId): FakeNutgram
    {
        return $this->bot
            ->setCommonChat(Chat::make(id: $chatId, type: 'private'))
            ->setCommonUser(User::make(id: $chatId, is_bot: false, first_name: 'Someone'));
    }

    /** What the bot did since the last reply(), as [method, params]. */
    private function calls(): array
    {
        return collect($this->bot->getRequestHistory())->map(function (array $entry) {
            $request = array_values($entry)[0];

            return ['method' => ltrim($request->getUri()->getPath(), '/'), 'params' => FakeNutgram::getActualData($request)];
        })->all();
    }

    private function say(string $text, int $chatId = self::TEACHER): array
    {
        $this->as($chatId)->hearText($text)->reply();

        return $this->calls();
    }

    private function tap(string $data, int $chatId = self::TEACHER): array
    {
        $this->as($chatId)->hearCallbackQueryData($data)->reply();

        return $this->calls();
    }

    private function voice(string $fileId, int $chatId = self::TEACHER, ?int $replyTo = null, string $kind = 'voice'): array
    {
        $message = [$kind => ['file_id' => $fileId, 'file_unique_id' => "u-{$fileId}", 'duration' => 2]];

        if ($replyTo !== null) {
            $message['reply_to_message'] = ['message_id' => $replyTo, 'date' => time(), 'chat' => ['id' => $chatId, 'type' => 'private']];
        }

        $this->as($chatId)->hearMessage($message)->reply();

        return $this->calls();
    }

    private function only(array $calls, string $method, ?int $chatId = null): array
    {
        return array_values(array_filter($calls, fn (array $call) => $call['method'] === $method
            && ($chatId === null || (int) ($call['params']['chat_id'] ?? 0) === $chatId)));
    }

    private function lesson(int $order): Lesson
    {
        return Lesson::where('order', $order)->firstOrFail();
    }

    private function tick(): array
    {
        (new LessonScheduler($this->bot))->scheduledTick($this->learner, CarbonImmutable::parse('2026-01-01 19:00:00'));

        return $this->calls();
    }

    // --- the learner gets the recording with the lesson -------------------------------------------------------

    public function test_a_lesson_with_a_recording_is_followed_by_it(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'rec-1', 'audio_kind' => 'voice']);

        $calls = $this->tick();
        $methods = array_column($calls, 'method');

        $voice = $this->only($calls, 'sendVoice', self::LEARNER);
        $this->assertCount(1, $voice);
        $this->assertSame('rec-1', $voice[0]['params']['voice']);
        $this->assertSame(BotMessages::lessonAudioCaption(), $voice[0]['params']['caption']);

        // Greeting, the lesson, then the recording: the phrase is read before it is heard.
        $this->assertSame(['sendMessage', 'sendMessage', 'sendVoice'], $methods);
        $this->assertStringContainsString('Oi, tudo bem?', $calls[1]['params']['text']);
    }

    public function test_a_lesson_without_a_recording_is_sent_as_before(): void
    {
        $calls = $this->tick();

        $this->assertSame(['sendMessage', 'sendMessage'], array_column($calls, 'method'));
        $this->assertSame(1, $this->learner->sends()->count());
    }

    public function test_a_recording_that_was_a_sound_file_is_sent_as_audio(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'file-1', 'audio_kind' => 'audio']);

        $calls = $this->tick();

        $this->assertSame([], $this->only($calls, 'sendVoice'));
        $this->assertSame('file-1', $this->only($calls, 'sendAudio', self::LEARNER)[0]['params']['audio']);
    }

    public function test_a_recording_that_telegram_refuses_does_not_stop_the_lesson(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'gone', 'audio_kind' => 'voice']);

        // Queued answers are used in order: the greeting and the lesson go through, the recording is refused.
        $sent = ['message_id' => 1, 'date' => time(), 'chat' => ['id' => self::LEARNER, 'type' => 'private']];
        $this->bot
            ->willReceive($sent)
            ->willReceive($sent)
            ->willReceive(['error_code' => 400, 'description' => 'Bad Request: wrong file identifier'], ok: false);

        $this->tick();

        $send = $this->learner->sends()->firstOrFail();
        $this->assertSame(SendStatus::Open, $send->status);
        $this->assertSame(1, $send->lesson->order);
    }

    public function test_today_sends_the_recording_again(): void
    {
        $lesson = $this->lesson(1);
        $lesson->update(['audio_file_id' => 'rec-1', 'audio_kind' => 'voice']);

        LessonSend::create([
            'learner_id' => $this->learner->id,
            'lesson_id' => $lesson->id,
            'delivery_type' => DeliveryType::Scheduled,
            'status' => SendStatus::Open,
            'sent_at' => now(),
        ]);

        $calls = $this->say('/today', self::LEARNER);

        $this->assertSame(['sendMessage', 'sendVoice'], array_column($calls, 'method'));
        $this->assertSame('rec-1', $calls[1]['params']['voice']);
    }

    // --- the teacher records ----------------------------------------------------------------------------------

    public function test_audio_shows_what_is_recorded_and_offers_the_first_lesson_without_a_recording(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'rec-1', 'audio_kind' => 'voice']);

        $calls = $this->say('/audio');

        $this->assertCount(1, $calls);
        $this->assertStringContainsString('Recordings: 1 of 3 lessons', $calls[0]['params']['text']);
        $this->assertStringContainsString('Missing: 2, 3', $calls[0]['params']['text']);
        $this->assertStringContainsString('audio:rec:2', json_encode($calls[0]['params']['reply_markup']));
    }

    public function test_audio_with_a_number_asks_for_that_phrase_and_the_next_voice_message_is_saved_for_it(): void
    {
        $asked = $this->say('/audio 2');

        $this->assertCount(1, $asked);
        $this->assertStringContainsString('Lesson 2: Tudo bem, e você?', $asked[0]['params']['text']);
        $this->assertStringContainsString('Send a voice message', $asked[0]['params']['text']);
        $this->assertSame(2, LessonAudioRecording::pending());

        $saved = $this->voice('teacher-rec-2');

        $this->assertSame('teacher-rec-2', $this->lesson(2)->audio_file_id);
        $this->assertSame('voice', $this->lesson(2)->audio_kind);
        $this->assertNull(LessonAudioRecording::pending());

        $this->assertCount(1, $saved);
        $this->assertStringContainsString('Saved for lesson 2 ✓', $saved[0]['params']['text']);
        $this->assertStringContainsString('2 lessons still have no recording', $saved[0]['params']['text']);
        // One tap goes on to the next lesson that has none.
        $this->assertStringContainsString('audio:rec:3', json_encode($saved[0]['params']['reply_markup']));
    }

    public function test_the_button_records_the_lesson_it_names_and_the_last_one_ends_without_a_button(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'rec-1', 'audio_kind' => 'voice']);
        $this->lesson(2)->update(['audio_file_id' => 'rec-2', 'audio_kind' => 'voice']);

        $asked = $this->tap('audio:rec:3');
        $this->assertStringContainsString('Lesson 3: Prazer em conhecer!', $this->only($asked, 'sendMessage')[0]['params']['text']);

        $saved = $this->voice('rec-3');

        $this->assertSame('rec-3', $this->lesson(3)->audio_file_id);
        $this->assertStringContainsString('Every lesson has a recording now', $saved[0]['params']['text']);
        $this->assertArrayNotHasKey('reply_markup', $saved[0]['params']);

        $status = $this->say('/audio');
        $this->assertStringContainsString('All 3 lessons have a recording', $status[0]['params']['text']);
    }

    public function test_recording_a_lesson_again_plays_the_old_recording_first_and_then_replaces_it(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'old', 'audio_kind' => 'voice']);

        $asked = $this->say('/audio 1');

        $this->assertSame(['sendVoice', 'sendMessage'], array_column($asked, 'method'));
        $this->assertSame('old', $asked[0]['params']['voice']);
        $this->assertStringContainsString('Send a new voice message to replace it', $asked[1]['params']['text']);

        $this->voice('new');

        $this->assertSame('new', $this->lesson(1)->audio_file_id);
    }

    public function test_cancel_keeps_what_was_there(): void
    {
        $this->lesson(1)->update(['audio_file_id' => 'old', 'audio_kind' => 'voice']);
        $this->say('/audio 1');

        $cancelled = $this->tap('audio:cancel');

        $this->assertStringContainsString('Cancelled', $this->only($cancelled, 'sendMessage')[0]['params']['text']);
        $this->assertNull(LessonAudioRecording::pending());

        $this->voice('stray');
        $this->assertSame('old', $this->lesson(1)->audio_file_id);
    }

    public function test_a_sound_file_is_accepted_too_and_remembered_as_audio(): void
    {
        $this->say('/audio 1');
        $this->voice('mp3-file', kind: 'audio');

        $this->assertSame('mp3-file', $this->lesson(1)->audio_file_id);
        $this->assertSame('audio', $this->lesson(1)->audio_kind);
    }

    public function test_a_lesson_that_does_not_exist_and_a_number_that_is_not_one_are_explained(): void
    {
        $this->assertStringContainsString('There is no lesson 99', $this->say('/audio 99')[0]['params']['text']);
        $this->assertStringContainsString('/audio 12', $this->say('/audio abc')[0]['params']['text']);
        $this->assertNull(LessonAudioRecording::pending());
    }

    public function test_only_the_teacher_can_record(): void
    {
        $this->assertSame([], $this->say('/audio', self::LEARNER));
        $this->assertSame([], $this->say('/audio 1', self::LEARNER));
        $this->assertSame([], $this->only($this->tap('audio:rec:1', self::LEARNER), 'sendMessage'));
        $this->assertNull(LessonAudioRecording::pending());
    }

    public function test_a_learners_voice_message_is_never_taken_for_a_lesson_recording(): void
    {
        $this->say('/audio 1');

        // The learner has no lesson open: their voice message gets the usual answer and changes no lesson.
        $this->voice('learner-voice', self::LEARNER);

        $this->assertNull($this->lesson(1)->audio_file_id);
        $this->assertSame(1, LessonAudioRecording::pending());
    }

    // --- recordings and reviews do not get mixed up -----------------------------------------------------------

    private function answerWaitingForReview(): Answer
    {
        $send = LessonSend::create([
            'learner_id' => $this->learner->id,
            'lesson_id' => $this->lesson(1)->id,
            'delivery_type' => DeliveryType::Scheduled,
            'status' => SendStatus::Answered,
            'sent_at' => now(),
        ]);

        return Answer::create([
            'learner_id' => $this->learner->id,
            'lesson_send_id' => $send->id,
            'attempt_number' => 1,
            'telegram_file_id' => 'learner-voice',
            'telegram_message_id' => 555,
            'received_at' => now(),
            'review_status' => 'waiting',
        ]);
    }

    public function test_tapping_not_yet_makes_the_next_voice_message_the_tip_and_not_a_lesson_recording(): void
    {
        $answer = $this->answerWaitingForReview();
        $this->say('/audio 2');

        $this->tap("rev:notyet:{$answer->id}");
        $this->assertNull(LessonAudioRecording::pending());

        $this->voice('tip');

        $this->assertNull($this->lesson(2)->audio_file_id);
        $this->assertSame('tip', Review::firstOrFail()->teacher_file_id);
    }

    public function test_a_voice_message_that_replies_to_the_tip_request_is_the_tip_even_while_a_lesson_waits(): void
    {
        $answer = $this->answerWaitingForReview();
        $review = Review::create(['answer_id' => $answer->id, 'verdict' => ReviewVerdict::NotYet, 'prompt_message_id' => 900]);
        $this->say('/audio 2');

        $this->voice('tip', replyTo: 900);

        $this->assertSame('tip', $review->fresh()->teacher_file_id);
        $this->assertNull($this->lesson(2)->audio_file_id);
        // The lesson is still waiting for its recording.
        $this->assertSame(2, LessonAudioRecording::pending());
    }

    // --- a deploy keeps the recordings ------------------------------------------------------------------------

    public function test_seeding_the_lessons_again_keeps_the_recordings(): void
    {
        Lesson::query()->delete();
        $this->seed(LessonSeeder::class);
        $this->lesson(1)->update(['audio_file_id' => 'rec-1', 'audio_kind' => 'voice']);

        $this->seed(LessonSeeder::class);

        $this->assertSame('rec-1', $this->lesson(1)->audio_file_id);
        $this->assertSame('voice', $this->lesson(1)->audio_kind);
    }
}
