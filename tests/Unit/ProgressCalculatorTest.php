<?php

namespace Tests\Unit;

use App\Enums\SendStatus;
use App\Models\Learner;
use App\Models\Lesson;
use App\Models\LessonSend;
use App\Services\ProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): Learner
    {
        return Learner::create(['telegram_id' => random_int(1, 999999999), 'status' => 'active']);
    }

    private function lesson(int $order = 1): Lesson
    {
        return Lesson::create([
            'order' => $order,
            'category' => 'Test',
            'phrase_pt' => 'Oi',
            'phrase_ru' => 'Привет',
            'phrase_en' => 'Hi',
            'pronunciation' => 'Ой',
            'has_variant' => false,
        ]);
    }

    private function send(Learner $learner, Lesson $lesson, SendStatus $status): LessonSend
    {
        return LessonSend::create([
            'learner_id' => $learner->id,
            'lesson_id' => $lesson->id,
            'delivery_type' => 'scheduled',
            'status' => $status,
            'sent_at' => now(),
        ]);
    }

    public function test_level_is_zero_with_no_answered_lessons(): void
    {
        $calculator = new ProgressCalculator();

        $this->assertSame(0, $calculator->level($this->learner()));
    }

    public function test_open_and_missed_sends_do_not_count_toward_level(): void
    {
        $learner = $this->learner();
        $this->send($learner, $this->lesson(1), SendStatus::Open);
        $this->send($learner, $this->lesson(2), SendStatus::Missed);

        $this->assertSame(0, (new ProgressCalculator())->level($learner));
    }

    public function test_each_distinct_answered_lesson_is_worth_two_percent(): void
    {
        $learner = $this->learner();

        for ($order = 1; $order <= 5; $order++) {
            $this->send($learner, $this->lesson($order), SendStatus::Answered);
        }

        $calculator = new ProgressCalculator();
        $this->assertSame(5, $calculator->answeredLessonsCount($learner));
        $this->assertSame(10, $calculator->level($learner));
    }

    public function test_the_same_lesson_answered_twice_only_counts_once(): void
    {
        $learner = $this->learner();
        $lesson = $this->lesson(1);
        $this->send($learner, $lesson, SendStatus::Missed);
        $this->send($learner, $lesson, SendStatus::Answered);

        $calculator = new ProgressCalculator();
        $this->assertSame(1, $calculator->answeredLessonsCount($learner));
        $this->assertSame(2, $calculator->level($learner));
    }

    public function test_fifty_answered_lessons_is_one_hundred_percent(): void
    {
        $learner = $this->learner();

        for ($order = 1; $order <= 50; $order++) {
            $this->send($learner, $this->lesson($order), SendStatus::Answered);
        }

        $this->assertSame(100, (new ProgressCalculator())->level($learner));
    }

    public function test_band_boundaries_match_the_new_gradual_scale(): void
    {
        $calculator = new ProgressCalculator();

        $this->assertSame('level_1', $calculator->band(0));
        $this->assertSame('level_1', $calculator->band(25));
        $this->assertSame('level_2', $calculator->band(26));
        $this->assertSame('level_2', $calculator->band(50));
        $this->assertSame('level_3', $calculator->band(51));
        $this->assertSame('level_3', $calculator->band(75));
        $this->assertSame('level_4', $calculator->band(76));
        $this->assertSame('level_4', $calculator->band(100));
    }

    public function test_band_colours_match_the_new_palette(): void
    {
        $calculator = new ProgressCalculator();

        $this->assertSame('#EDB21D', $calculator->colorHex('level_1'));
        $this->assertSame('#C8BE24', $calculator->colorHex('level_2'));
        $this->assertSame('#8DBA34', $calculator->colorHex('level_3'));
        $this->assertSame('#3FAE6A', $calculator->colorHex('level_4'));
    }
}
