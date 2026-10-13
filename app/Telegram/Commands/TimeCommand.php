<?php

namespace App\Telegram\Commands;

use App\Models\Learner;
use App\Support\BotMessages;
use App\Support\TimeKeyboard;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.9: changes the daily lesson time, with the same buttons/typed formats as §3.1.
 */
class TimeCommand extends Command
{
    protected string $command = 'time';

    protected ?string $description = 'Mudar o horário da lição diária.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null) {
            return;
        }

        $bot->sendMessage(BotMessages::welcomeAndTimeQuestion(), reply_markup: TimeKeyboard::build());
    }
}
