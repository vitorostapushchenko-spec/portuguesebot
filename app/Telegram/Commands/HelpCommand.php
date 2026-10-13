<?php

namespace App\Telegram\Commands;

use App\Support\BotMessages;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

class HelpCommand extends Command
{
    protected string $command = 'help';

    protected ?string $description = 'Como o bot funciona.';

    public function handle(Nutgram $bot): void
    {
        $bot->sendMessage(BotMessages::help());
    }
}
