<?php

namespace Tests\Feature;

use App\Enums\LearnerStatus;
use App\Enums\SendStatus;
use App\Models\Learner;
use App\Models\Lesson;
use App\Services\LessonScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class LessonSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['course.total_lessons' => 3, 'course.pause_after_days' => 3]);

        for ($order = 1; $order <= 3; $order++) {
            Lesson::create([
                'order' => $order,
                'category' => 'Test',
                'phrase_pt' => "Frase {$order}",
                'phrase_ru' => "Фраза {$order}",
                'phrase_en' => "Phrase {$order}",
                'pronunciation' => "Фраза {$order}",
                'has_variant' => false,
            ]);
        }
    }

    private function learner(): Learner
    {
        return Learner::create([
            'telegram_id' => random_int(1, 999999999),
            'lesson_time' => '19:00:00',
            'status' => LearnerStatus::Active,
        ]);
    }

    public function test_first_tick_sends_lesson_one(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();

        (new LessonScheduler($bot))->scheduledTick($learner, CarbonImmutable::now());

        $this->assertSame(1, $learner->sends()->count());
        $send = $learner->sends()->first();
        $this->assertSame(1, $send->lesson->order);
        $this->assertSame(SendStatus::Open, $send->status);
    }

    public function test_unanswered_lesson_repeats_and_counts_as_missed_next_time(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();
        $scheduler = new LessonScheduler($bot);
        $day = CarbonImmutable::parse('2026-01-01 19:00:00');

        $scheduler->scheduledTick($learner, $day);
        $scheduler->scheduledTick($learner, $day->addDay());

        $learner->refresh();
        $this->assertSame(2, $learner->sends()->count());
        $this->assertSame(1, $learner->sends()->where('status', SendStatus::Missed)->count());
        $this->assertSame(1, $learner->consecutive_unanswered);
        $this->assertSame(LearnerStatus::Active, $learner->status);
    }

    public function test_pauses_after_configured_unanswered_days(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();
        $scheduler = new LessonScheduler($bot);
        $day = CarbonImmutable::parse('2026-01-01 19:00:00');

        // pause_after_days = 3: three unanswered sends, the 4th tick pauses instead of sending again.
        for ($i = 0; $i < 4; $i++) {
            $scheduler->scheduledTick($learner, $day->addDays($i));
        }

        $learner->refresh();
        $this->assertSame(LearnerStatus::PausedInactive, $learner->status);
        $this->assertSame(3, $learner->sends()->count());
    }

    public function test_answered_lesson_advances_to_the_next_one(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();
        $scheduler = new LessonScheduler($bot);
        $day = CarbonImmutable::parse('2026-01-01 19:00:00');

        $scheduler->scheduledTick($learner, $day);
        $learner->currentSend()->update(['status' => SendStatus::Answered]);

        $scheduler->scheduledTick($learner, $day->addDay());
        $learner->refresh();

        $this->assertSame(2, $learner->current_lesson_number);
        $this->assertSame(0, $learner->consecutive_unanswered);
        $this->assertSame(2, $learner->sends()->count());
    }

    public function test_next_is_refused_before_the_first_lesson_has_ever_been_sent(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();

        $result = (new LessonScheduler($bot))->sendNext($learner, CarbonImmutable::parse('2026-01-01 10:00:00'));

        $this->assertSame('refused', $result);
        $this->assertSame(0, $learner->sends()->count());
        $this->assertSame(1, $learner->current_lesson_number);
    }

    public function test_course_finishes_exactly_once_after_the_last_lesson(): void
    {
        $bot = Nutgram::fake();
        $learner = $this->learner();
        $learner->update(['current_lesson_number' => 3]);
        $scheduler = new LessonScheduler($bot);
        $day = CarbonImmutable::parse('2026-01-01 19:00:00');

        $scheduler->scheduledTick($learner, $day);
        $learner->currentSend()->update(['status' => SendStatus::Answered]);

        $scheduler->scheduledTick($learner, $day->addDay());
        $learner->refresh();

        $this->assertSame(LearnerStatus::Finished, $learner->status);
        // No new lesson_send was created for a "lesson 4" that doesn't exist.
        $this->assertSame(1, $learner->sends()->count());
    }
}
