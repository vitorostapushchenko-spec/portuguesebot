<?php

namespace Tests\Feature;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Models\Lesson;
use App\Services\TimeSetter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class TimeSetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Lesson::create([
            'order' => 1,
            'category' => 'Test',
            'phrase_pt' => 'Oi',
            'phrase_ru' => 'Привет',
            'phrase_en' => 'Hi',
            'pronunciation' => 'Ой',
            'has_variant' => false,
        ]);
    }

    public function test_first_ever_time_sends_lesson_one_immediately(): void
    {
        $bot = Nutgram::fake();
        $learner = Learner::create([
            'telegram_id' => random_int(1, 999999999),
            'status' => LearnerStatus::SettingUp,
        ]);

        (new TimeSetter())->apply($learner, 23, 0, $bot);

        $learner->refresh();
        $this->assertSame(LearnerStatus::Active, $learner->status);
        $this->assertSame(1, $learner->sends()->count());
    }

    public function test_changing_the_time_later_does_not_resend_a_lesson(): void
    {
        $bot = Nutgram::fake();
        $learner = Learner::create([
            'telegram_id' => random_int(1, 999999999),
            'status' => LearnerStatus::Active,
            'lesson_time' => '10:00:00',
        ]);

        (new TimeSetter())->apply($learner, 23, 0, $bot);

        $this->assertSame(0, $learner->sends()->count());
    }
}
