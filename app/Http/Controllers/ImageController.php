<?php

namespace App\Http\Controllers;

use App\Http\Requests\Images\GetBookImageRequest;
use App\Http\Requests\Images\GetKanjiImageRequest;
// form requests
use App\Services\ImageService;
use Illuminate\Support\Facades\Auth;
// services
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    private $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function getBookImage($fileName, GetBookImageRequest $request)
    {
        $userId = Auth::user()->id;

        try {
            $imagePath = $this->imageService->getBookImage($userId, $fileName);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->file($imagePath);
    }

    public function getKanjiImage($fileName, GetKanjiImageRequest $request)
    {
        $imagePath = Storage::path('/images/kanjivg/'.$fileName);

        return response()->file($imagePath);
    }
}
