<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class LessonSeeder extends Seeder
{
    /**
     * Course content lives as data (database/data/lessons.json), not code, so
     * phrases/images can be edited and re-seeded without touching the bot (spec §6/§7).
     */
    public function run(): void
    {
        $path = database_path('data/lessons.json');
        $lessons = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($lessons as $lesson) {
            Lesson::updateOrCreate(
                ['order' => $lesson['order']],
                [
                    'category' => $lesson['category'],
                    'phrase_pt' => $lesson['phrase_pt'],
                    'phrase_ru' => $lesson['phrase_ru'],
                    'phrase_en' => $lesson['phrase_en'],
                    'pronunciation' => $lesson['pronunciation'],
                    'has_variant' => $lesson['has_variant'],
                    'image_path' => "lessons/{$lesson['order']}.png",
                ]
            );
        }
    }
}
