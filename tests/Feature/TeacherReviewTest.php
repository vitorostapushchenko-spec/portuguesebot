<?php

namespace Tests\Feature;

use App\Enums\AnswerReviewStatus;
use App\Enums\DeliveryType;
use App\Enums\LearnerStatus;
use App\Enums\ReviewVerdict;
use App\Enums\SendStatus;
use App\Models\Answer;
use App\Models\Learner;
use App\Models\Lesson;
use App\Models\LessonSend;
use App\Models\Review;
use App\Support\BotMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Chat\Chat;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;
use Tests\TestCase;

/**
 * The teacher's Good / Not yet buttons (spec §3.4, changed on request): Good sends "Молодец!" to the learner at
 * once, with no recording and no confirmation; Not yet still asks for a tip and goes Skip -> Send / Start over.
 */
class TeacherReviewTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER = 777000111;

    private const LEARNER = 4242;

    private FakeNutgram $bot;

    private Learner $learner;

    private Answer $answer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['course.teacher_chat_id' => self::TEACHER, 'course.total_lessons' => 50]);

        $lesson = Lesson::create([
            'order' => 3,
            'category' => 'Test',
            'phrase_pt' => 'Bom dia!',
            'phrase_ru' => 'Доброе утро!',
            'phrase_en' => 'Good morning!',
            'pronunciation' => 'Бом ди́а',
            'has_variant' => false,
        ]);

        $this->learner = Learner::create([
            'telegram_id' => self::LEARNER,
            'first_name' => 'Anna',
            'lesson_time' => '19:00:00',
            'status' => LearnerStatus::Active,
        ]);

        $send = LessonSend::create([
            'learner_id' => $this->learner->id,
            'lesson_id' => $lesson->id,
            'delivery_type' => DeliveryType::Scheduled,
            'status' => SendStatus::Answered,
            'sent_at' => now(),
        ]);

        $this->answer = Answer::create([
            'learner_id' => $this->learner->id,
            'lesson_send_id' => $send->id,
            'attempt_number' => 1,
            'telegram_file_id' => 'learner-voice',
            'telegram_message_id' => 555,
            'received_at' => now(),
            'review_status' => 'waiting',
        ]);

        $this->bot = Nutgram::fake();

        // The fake bot starts without handlers. Register the real ones, so the tests also cover the routing of the
        // buttons ("rev:good:{answerId}" and the rest) and not only the handler methods.
        (function (Nutgram $bot) {
            require base_path('routes/telegram.php');
        })($this->bot);
    }

    /** The teacher (or someone else, with $chatId) taps a button; returns what the bot did because of it, as [method, parameters]. */
    private function tap(string $data, int $chatId = self::TEACHER): array
    {
        // reply() starts the history of the fake bot over, so its history is exactly what this tap caused.
        $this->bot
            ->setCommonChat(Chat::make(id: $chatId, type: 'private'))
            ->setCommonUser(User::make(id: $chatId, is_bot: false, first_name: 'Someone'))
            ->hearCallbackQueryData($data)
            ->reply();

        return collect($this->bot->getRequestHistory())->map(function (array $entry) {
            $request = array_values($entry)[0];

            return ['method' => ltrim($request->getUri()->getPath(), '/'), 'params' => FakeNutgram::getActualData($request)];
        })->all();
    }

    /** The messages (not the button acknowledgement) in a list of calls, for one chat. */
    private function messagesTo(array $calls, int $chatId, string $method = 'sendMessage'): array
    {
        return array_values(array_filter($calls, fn (array $call) => $call['method'] === $method && (int) ($call['params']['chat_id'] ?? 0) === $chatId));
    }

    private function good(): string
    {
        return "rev:good:{$this->answer->id}";
    }

    private function notYet(): string
    {
        return "rev:notyet:{$this->answer->id}";
    }

    // --- Good: sent at once ---------------------------------------------------------------------------------

    public function test_good_sends_the_praise_to_the_learner_at_once_as_a_reply_to_their_answer(): void
    {
        $calls = $this->tap($this->good());

        $toLearner = $this->messagesTo($calls, self::LEARNER);

        $this->assertCount(1, $toLearner);
        $this->assertSame("Отзыв на урок 3\nBom dia!\nМолодец! ✅ Всё правильно!", $toLearner[0]['params']['text']);
        $this->assertSame(555, (int) $toLearner[0]['params']['reply_to_message_id']);
    }

    public function test_the_praise_is_short_three_or_four_words_and_starts_with_molodets(): void
    {
        $praise = BotMessages::verdictGood();

        // Count the words (tokens with a letter in them), not the check mark.
        $words = array_filter(preg_split('/\s+/u', $praise), fn (string $token) => preg_match('/\p{L}/u', $token) === 1);

        $this->assertStringStartsWith('Молодец!', $praise);
        $this->assertGreaterThanOrEqual(3, count($words));
        $this->assertLessThanOrEqual(4, count($words));
    }

    public function test_good_asks_the_teacher_for_nothing_and_has_no_confirmation_step(): void
    {
        $calls = $this->tap($this->good());

        foreach ($calls as $call) {
            $this->assertArrayNotHasKey('reply_markup', $call['params'], "{$call['method']} came with buttons");
        }

        $this->assertSame([], $this->messagesTo($calls, self::LEARNER, 'sendVoice'));
        $this->assertSame([], $this->messagesTo($calls, self::TEACHER, 'sendVoice'));

        $toTeacher = $this->messagesTo($calls, self::TEACHER);
        $this->assertCount(1, $toTeacher);
        $this->assertSame('Sent to Anna ✓', $toTeacher[0]['params']['text']);
    }

    public function test_good_is_recorded_as_sent_and_the_answer_as_reviewed(): void
    {
        $this->tap($this->good());

        $review = Review::firstOrFail();

        $this->assertSame(ReviewVerdict::Good, $review->verdict);
        $this->assertNotNull($review->sent_at);
        $this->assertNull($review->teacher_file_id);
        $this->assertSame(AnswerReviewStatus::Reviewed, $this->answer->fresh()->review_status);
    }

    public function test_tapping_good_twice_praises_once(): void
    {
        $this->tap($this->good());
        $second = $this->tap($this->good());

        $this->assertSame([], $this->messagesTo($second, self::LEARNER));

        $toTeacher = $this->messagesTo($second, self::TEACHER);
        $this->assertCount(1, $toTeacher);
        $this->assertSame('Already sent to Anna ✓ (✅ Good)', $toTeacher[0]['params']['text']);
        $this->assertSame(1, Review::count());
    }

    public function test_good_after_a_not_yet_that_was_never_finished_sends_no_correction(): void
    {
        $this->tap($this->notYet());
        Review::firstOrFail()->update(['teacher_file_id' => 'teacher-tip']);

        $calls = $this->tap($this->good());

        $this->assertCount(1, $this->messagesTo($calls, self::LEARNER));
        $this->assertSame([], $this->messagesTo($calls, self::LEARNER, 'sendVoice'));
        $this->assertSame(1, Review::count());
        $this->assertSame(ReviewVerdict::Good, Review::firstOrFail()->verdict);
        $this->assertNull(Review::firstOrFail()->teacher_file_id);
    }

    public function test_good_from_someone_who_is_not_the_teacher_does_nothing(): void
    {
        $calls = $this->tap($this->good(), chatId: 999);

        $this->assertSame([], $this->messagesTo($calls, self::LEARNER));
        $this->assertSame(0, Review::count());
        $this->assertSame(AnswerReviewStatus::Waiting, $this->answer->fresh()->review_status);
    }

    public function test_good_for_an_answer_that_does_not_exist_does_nothing(): void
    {
        $calls = $this->tap('rev:good:99999');

        $this->assertSame([], $this->messagesTo($calls, self::LEARNER));
        $this->assertSame(0, Review::count());
    }

    public function test_a_learner_who_blocked_the_bot_is_marked_and_the_teacher_is_told(): void
    {
        // The first call is the acknowledgement of the tap; the second, the message to the learner, is refused.
        $this->bot->willReceive(true)->willReceive(['error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], ok: false);

        $calls = $this->tap($this->good());

        $toTeacher = $this->messagesTo($calls, self::TEACHER);
        $this->assertSame('Could not deliver the feedback — Anna has blocked the bot.', end($toTeacher)['params']['text']);
        $this->assertSame(LearnerStatus::Blocked, $this->learner->fresh()->status);
        $this->assertNull(Review::firstOrFail()->sent_at);
        $this->assertSame(AnswerReviewStatus::Waiting, $this->answer->fresh()->review_status);
    }

    // --- Not yet: unchanged ---------------------------------------------------------------------------------

    public function test_not_yet_still_asks_for_a_spoken_tip_and_sends_nothing_to_the_learner_yet(): void
    {
        $calls = $this->tap($this->notYet());

        $this->assertSame([], $this->messagesTo($calls, self::LEARNER));

        $toTeacher = $this->messagesTo($calls, self::TEACHER);
        $this->assertCount(1, $toTeacher);
        $this->assertStringContainsString('Record the phrase the right way', $toTeacher[0]['params']['text']);
        $this->assertStringContainsString('rev:skip:', json_encode($toTeacher[0]['params']['reply_markup']));

        $review = Review::firstOrFail();
        $this->assertSame(ReviewVerdict::NotYet, $review->verdict);
        $this->assertNull($review->sent_at);
    }

    public function test_not_yet_with_a_tip_goes_to_the_learner_as_text_and_voice_after_the_teacher_confirms(): void
    {
        $this->tap($this->notYet());
        $review = Review::firstOrFail();
        $review->update(['teacher_file_id' => 'teacher-tip']);

        $confirm = $this->tap("rev:skip:{$review->id}");
        $toTeacher = $this->messagesTo($confirm, self::TEACHER);
        $this->assertStringContainsString("Send to Anna?\n❌ Not yet + your voice message", $toTeacher[0]['params']['text']);

        $sent = $this->tap("rev:send:{$review->id}");

        $text = $this->messagesTo($sent, self::LEARNER);
        $voice = $this->messagesTo($sent, self::LEARNER, 'sendVoice');

        $this->assertCount(1, $text);
        $this->assertStringContainsString('Пока не совсем', $text[0]['params']['text']);
        $this->assertSame(555, (int) $text[0]['params']['reply_to_message_id']);
        $this->assertCount(1, $voice);
        $this->assertSame('teacher-tip', $voice[0]['params']['voice']);
        $this->assertSame('Sent to Anna ✓', $this->messagesTo($sent, self::TEACHER)[0]['params']['text']);
        $this->assertNotNull($review->fresh()->sent_at);
        $this->assertSame(AnswerReviewStatus::Reviewed, $this->answer->fresh()->review_status);
    }

    public function test_starting_over_lets_the_teacher_choose_again_and_good_then_sends_the_praise(): void
    {
        $this->tap($this->notYet());
        $review = Review::firstOrFail();

        $again = $this->tap("rev:redo:{$review->id}");
        $this->assertSame('Choose again:', $this->messagesTo($again, self::TEACHER)[0]['params']['text']);
        $this->assertSame(0, Review::count());

        $calls = $this->tap($this->good());

        $this->assertCount(1, $this->messagesTo($calls, self::LEARNER));
        $this->assertStringContainsString('Молодец!', $this->messagesTo($calls, self::LEARNER)[0]['params']['text']);
    }
}
