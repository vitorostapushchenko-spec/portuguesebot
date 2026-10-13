<?php

namespace App\Console\Commands;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Services\LessonScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class DispatchDueLessons extends Command
{
    protected $signature = 'lessons:dispatch-due';

    protected $description = "Send the daily lesson (or its repeat) to every learner whose chosen time is now.";

    public function handle(Nutgram $bot): void
    {
        $now = CarbonImmutable::now(config('course.timezone'));
        $currentTime = $now->format('H:i');
        $scheduler = new LessonScheduler($bot);

        Learner::query()
            ->where('status', LearnerStatus::Active)
            ->whereNotNull('lesson_time')
            ->get()
            ->filter(fn (Learner $learner) => substr((string) $learner->lesson_time, 0, 5) === $currentTime)
            ->each(function (Learner $learner) use ($scheduler, $now): void {
                try {
                    $scheduler->scheduledTick($learner, $now);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }
}
