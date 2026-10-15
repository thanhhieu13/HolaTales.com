<?php

namespace Database\Seeders;

use App\Models\Story;
use Illuminate\Database\Seeder;

class SpanishStorySeeder extends Seeder
{
    public function run(): void
    {
        $stories = [
            ['la-panaderia-de-lucia', 'La panadería de Lucía', 'A1', 'Everyday life', 'Lucía ghé tiệm bánh mỗi sáng và chia sẻ một món quà nhỏ.',
                'Lucía vive en un pueblo pequeño. Cada mañana visita una panadería cerca de su casa. El panadero sonríe y dice: Buenos días. Hoy, Lucía compra pan para su abuela. En la puerta ve a un niño. El niño tiene hambre. Lucía le da un poco de pan. El niño sonríe. Es un regalo pequeño, pero cambia su día. Lucía vuelve a casa contenta.',
                ['panadería' => 'bakery', 'pueblo' => 'town', 'pequeño' => 'small', 'mañana' => 'morning', 'panadero' => 'baker', 'abuela' => 'grandmother', 'niño' => 'boy', 'hambre' => 'hunger', 'regalo' => 'gift', 'contenta' => 'happy'], false],
            ['una-carta-junto-al-mar', 'Una carta junto al mar', 'A2', 'Adventure', 'Một lá thư bên bờ biển mang đến một tình bạn mới.',
                'Cada verano, Ana visita a su tío en un pueblo junto al mar. Una tarde encuentra una botella en la playa. Dentro hay una carta con una pregunta: ¿Qué te hace feliz? Ana piensa en su familia, sus amigos y el sonido de las olas. Escribe una respuesta y la deja en la biblioteca del pueblo. Una semana después, llega un desconocido. Él escribió la primera carta. Hablan durante horas y comparten sus historias. Ana descubre que una pregunta sencilla puede iniciar una amistad maravillosa.',
                ['verano' => 'summer', 'playa' => 'beach', 'botella' => 'bottle', 'carta' => 'letter', 'olas' => 'waves', 'desconocido' => 'stranger', 'amistad' => 'friendship', 'sencilla' => 'simple'], false],
            ['el-ultimo-tren', 'El último tren', 'B1', 'Mystery', 'Chiếc đồng hồ bỏ quên trên chuyến tàu cuối ngày.',
                'Daniel casi perdió el último tren. Cuando encontró un asiento, vio un reloj antiguo a su lado. En la parte de atrás había un mensaje: Recuerda el jardín. En la siguiente estación subió una mujer mayor. Buscaba algo con una expresión de preocupación. Daniel le mostró el reloj. La mujer sonrió con alivio. El reloj había pertenecido a su marido, que años antes había plantado un jardín para toda la familia. Ella contó la historia de las flores que todavía crecían allí. Un viaje cotidiano se convirtió en un recuerdo inolvidable.',
                ['reloj' => 'watch', 'asiento' => 'seat', 'jardín' => 'garden', 'preocupación' => 'worry', 'alivio' => 'relief', 'marido' => 'husband', 'cotidiano' => 'everyday', 'inolvidable' => 'unforgettable'], true],
        ];
        foreach ($stories as [$slug,$title,$level,$topic,$summary,$body,$glossary,$premium]) {
            Story::firstOrCreate(['slug' => $slug], compact('title', 'level', 'topic', 'summary', 'body', 'glossary') + ['language' => 'spanish', 'glossary_language' => 'en', 'is_premium' => $premium, 'published_at' => now()]);
        }
    }
}
