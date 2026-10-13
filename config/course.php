<?php

return [
    // The one Telegram account allowed to review answers and use teacher commands (3.9).
    'teacher_chat_id' => env('TEACHER_CHAT_ID'),

    // All lesson-time scheduling is in this timezone, never asked of the learner (3. Time).
    'timezone' => 'America/Sao_Paulo',

    'total_lessons' => 50,

    // After this many unanswered sends of the same lesson, the bot pauses it (3.7).
    'pause_after_days' => 7,

    // Local time the teacher's daily pending-review summary is sent, if there is one (3.4).
    'daily_summary_time' => '09:00',

    // Learner may pick any half-hour slot in this range, all 24 hours (3.1).
    'lesson_time_choices' => ['start' => 0, 'end' => 23],
];
