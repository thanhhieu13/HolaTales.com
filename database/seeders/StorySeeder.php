<?php

namespace Database\Seeders;

use App\Models\Story;
use Illuminate\Database\Seeder;

class StorySeeder extends Seeder
{
    public function run(): void
    {
        $stories = [
            ['the-little-bakery', 'The Little Bakery', 'A1', 'Everyday life', 'Một buổi sáng, Mia tìm thấy niềm vui trong tiệm bánh nhỏ.',
                'Mia lives in a small town. Every morning, she walks to a little bakery. The baker smiles and says hello. Today, Mia buys bread for her grandmother. She sees a young boy at the door. He looks hungry. Mia gives him a piece of bread. The boy smiles. It is a small gift, but it makes a big difference. Mia walks home with a warm heart.',
                ['bakery' => 'tiệm bánh', 'baker' => 'thợ làm bánh', 'hungry' => 'đói', 'gift' => 'món quà', 'difference' => 'sự khác biệt', 'bread' => 'bánh mì', 'grandmother' => 'bà', 'warm' => 'ấm áp'], false],
            ['a-letter-by-the-sea', 'A Letter by the Sea', 'A2', 'Adventure', 'Một lá thư trên bãi biển mở đầu cho một tình bạn bất ngờ.',
                'Every summer, Anna visits her uncle by the sea. One afternoon, she finds a bottle on the beach. Inside the bottle is a letter. The writer asks a simple question: What makes you happy? Anna thinks about her family, her friends, and the sound of the waves. She writes an answer and leaves it at the village library. A week later, a stranger arrives with a smile. He wrote the first letter. They spend the afternoon sharing stories. Anna discovers that a small question can begin a wonderful friendship.',
                ['bottle' => 'chai', 'beach' => 'bãi biển', 'letter' => 'lá thư', 'waves' => 'sóng', 'stranger' => 'người lạ', 'friendship' => 'tình bạn', 'sharing' => 'chia sẻ', 'discovers' => 'khám phá'], false],
            ['the-last-train', 'The Last Train', 'B1', 'Mystery', 'Một chuyến tàu cuối ngày và chiếc đồng hồ chứa một bí mật.',
                'Daniel almost missed the last train. When he finally sat down, he noticed an old watch on the seat beside him. A message was engraved on the back: Remember the garden. At the next station, an elderly woman entered the carriage. She searched each seat with worried eyes. Daniel showed her the watch. Her face changed immediately. It had belonged to her husband, who had planted a garden for their family many years ago. She thanked Daniel and told him about the flowers that still grew there. What seemed like an ordinary journey became a story he would never forget.',
                ['engraved' => 'khắc', 'carriage' => 'toa tàu', 'elderly' => 'cao tuổi', 'belonged' => 'thuộc về', 'ordinary' => 'bình thường', 'journey' => 'hành trình', 'worried' => 'lo lắng'], true],
        ];
        foreach ($stories as [$slug,$title,$level,$topic,$summary,$body,$glossary,$premium]) {
            Story::firstOrCreate(['slug' => $slug], compact('title', 'level', 'topic', 'summary', 'body', 'glossary') + ['language' => 'english', 'is_premium' => $premium, 'published_at' => now()]);
        }
    }
}
