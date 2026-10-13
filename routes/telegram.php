<?php

use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\LearnersCommand;
use App\Telegram\Commands\LevelCommand;
use App\Telegram\Commands\NextCommand;
use App\Telegram\Commands\PauseCommand;
use App\Telegram\Commands\PendingCommand;
use App\Telegram\Commands\ProgressCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Commands\TimeCommand;
use App\Telegram\Commands\TodayCommand;
use App\Telegram\Handlers\LessonAudioHandler;
use App\Telegram\Handlers\NonVoiceReminderHandler;
use App\Telegram\Handlers\PendingCallbackHandler;
use App\Telegram\Handlers\TeacherReviewCallbackHandler;
use App\Telegram\Handlers\TimeCallbackHandler;
use App\Telegram\Handlers\TypedTextHandler;
use App\Telegram\Handlers\VoiceMessageHandler;
use SergiX44\Nutgram\Nutgram;

/* @var $bot Nutgram */

// Learner commands (§3.9).
$bot->registerCommand(StartCommand::class);
$bot->registerCommand(TimeCommand::class);
$bot->registerCommand(NextCommand::class);
$bot->registerCommand(ProgressCommand::class);
$bot->registerCommand(TodayCommand::class);
$bot->registerCommand(PauseCommand::class);
$bot->registerCommand(HelpCommand::class);

// Teacher-only commands (§3.9); each checks the caller internally.
$bot->registerCommand(PendingCommand::class);
$bot->registerCommand(LearnersCommand::class);
$bot->registerCommand(LevelCommand::class);

// Teacher-only: the recording of each phrase, sent to learners together with the lesson.
// "/audio" shows what is recorded and what is missing; "/audio 12" records lesson 12.
$bot->onCommand('audio', [LessonAudioHandler::class, 'status']);
$bot->onCommand('audio {number}', [LessonAudioHandler::class, 'record']);
$bot->onCallbackQueryData('audio:rec:{order}', [LessonAudioHandler::class, 'recordFromButton'])->where('order', '\d+');
$bot->onCallbackQueryData('audio:cancel', [LessonAudioHandler::class, 'cancel']);

// Time picker (onboarding §3.1 and /time §3.9).
$bot->onCallbackQueryData('time:{time}', [TimeCallbackHandler::class, 'handle']);

// Teacher review flow (§3.4).
$bot->onCallbackQueryData('rev:{verdict}:{answerId}', [TeacherReviewCallbackHandler::class, 'verdict'])
    ->where('verdict', 'good|notyet');
$bot->onCallbackQueryData('rev:skip:{reviewId}', [TeacherReviewCallbackHandler::class, 'skip']);
$bot->onCallbackQueryData('rev:send:{reviewId}', [TeacherReviewCallbackHandler::class, 'send']);
$bot->onCallbackQueryData('rev:redo:{reviewId}', [TeacherReviewCallbackHandler::class, 'redo']);

// Daily summary button and /pending item buttons (§3.4/§3.9).
$bot->onCallbackQueryData('pending:list', [PendingCallbackHandler::class, 'list']);
$bot->onCallbackQueryData('pending:open:{answerId}', [PendingCallbackHandler::class, 'open']);

// Voice answers from learners, and from the teacher: voice tips/corrections (§3.3/§3.4) and lesson recordings (/audio).
$bot->onVoice([VoiceMessageHandler::class, 'handle']);
$bot->onAudio([VoiceMessageHandler::class, 'handle']);

// Anything else sent instead of a voice message, while a lesson is open (§3.3).
$bot->onPhoto([NonVoiceReminderHandler::class, 'handle']);
$bot->onSticker([NonVoiceReminderHandler::class, 'handle']);
$bot->onVideo([NonVoiceReminderHandler::class, 'handle']);
$bot->onVideoNote([NonVoiceReminderHandler::class, 'handle']);
$bot->onDocument([NonVoiceReminderHandler::class, 'handle']);
$bot->onAnimation([NonVoiceReminderHandler::class, 'handle']);

// Typed times (§3.1) and the "please send a voice message" reminder (§3.3).
// Constrained to not start with "/" so it never shadows a command above.
$bot->onText('{text}', [TypedTextHandler::class, 'handle'])->where('text', '[^\/].*');
