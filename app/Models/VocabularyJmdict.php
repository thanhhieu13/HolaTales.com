<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabularyJmdict extends Model
{
    use HasFactory;

    protected $table = 'dict_jp_jmdict';

    protected $fillable = [

    ];

    public function words()
    {
        return $this->hasMany(VocabularyJmdictWord::class, 'dict_jp_jmdict_id');
    }

    public function readings()
    {
        return $this->hasMany(VocabularyJmdictReading::class, 'dict_jp_jmdict_id');
    }
}
