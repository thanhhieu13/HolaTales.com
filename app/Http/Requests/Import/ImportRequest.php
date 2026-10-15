<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'importFile' => 'required_if:importType,e-book|file|extensions:epub|max:20480',
            'importText' => 'required_if:importType,plain-text,text-file,youtube,website|string|max:250000',
            'importSubtitles' => 'required_if:importType,subtitle-file,jellyfin-subtitle|json|max:2000000',
            'importType' => 'required|in:e-book,jellyfin-subtitle,subtitle-file,plain-text,text-file,youtube,website',
            'eBookChapterSortMethod' => 'required|in:default,spine',
            'textProcessingMethod' => 'required|in:detailed',
            'bookId' => 'required|integer|gte:-1',
            'bookName' => 'required_if:bookId,-1|nullable|string|max:128',
            'chapterName' => 'required|string|max:128',
            'maximumCharactersPerChapter' => 'required|integer|gte:200|lte:20000',
        ];
    }
}
