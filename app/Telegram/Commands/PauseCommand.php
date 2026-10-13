<?php

namespace App\Telegram\Commands;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Support\BotMessages;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.9: stops the daily lessons until the learner sends /start. Progress is kept.
 */
class PauseCommand extends Command
{
    protected string $command = 'pause';

    protected ?string $description = 'Pausar as lições diárias.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null) {
            return;
        }

        $learner->update(['status' => LearnerStatus::Paused]);
        $bot->sendMessage(BotMessages::pauseCommand());
    }
}
