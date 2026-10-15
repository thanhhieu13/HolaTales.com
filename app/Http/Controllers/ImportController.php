<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\GetSubtitleFileContentRequest;
// services
use App\Http\Requests\Import\GetWebsiteTextRequest;
use App\Http\Requests\Import\GetYoutubeSubtitlesRequest;
// request classes
use App\Http\Requests\Import\ImportRequest;
use App\Services\ImportService;
use App\Services\TempFileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ImportController extends Controller
{
    private $importMethods = [
        'e-book' => 'e-book',
        'jellyfin-subtitle' => 'subtitle',
        'subtitle-file' => 'subtitle',
        'plain-text' => 'text',
        'text-file' => 'text',
        'youtube' => 'text',
        'website' => 'text',
    ];

    private $importService;

    private $tempFileService;

    public function __construct(ImportService $importService, TempFileService $tempFileService)
    {
        $this->importService = $importService;
        $this->tempFileService = $tempFileService;
    }

    public function import(ImportRequest $request)
    {
        $userId = Auth::user()->id;
        $userUuid = Auth::user()->uuid;
        $importType = $request->post('importType');
        $textProcessingMethod = $request->post('textProcessingMethod');
        $eBookChapterSortMethod = $request->post('eBookChapterSortMethod');
        $bookId = $request->post('bookId');
        $bookName = $request->post('bookName');
        $chapterName = $request->post('chapterName');
        $chunkSize = intval($request->post('maximumCharactersPerChapter'));
        $importMethod = $this->importMethods[$importType];

        if ($importMethod == 'e-book') {
            $importFile = $request->file('importFile');
        } elseif ($importMethod == 'text') {
            $importText = $request->post('importText');
        } elseif ($importMethod == 'subtitle') {
            $importSubtitles = $request->post('importSubtitles');
        }

        // move file to temp folder
        if (isset($importFile)) {
            try {
                $fileName = $this->tempFileService->moveFileToTempFolder($userId, $importFile);
            } catch (\Exception $e) {
                if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                    throw $e;
                }
                abort(502, 'Không xử lý được nội dung. Kiểm tra nguồn và thử lại.');
            }
        }

        // import
        try {
            if ($importMethod === 'e-book') {
                // e-book
                $this->importService->importBook($userId, $userUuid, $chunkSize, $eBookChapterSortMethod, $textProcessingMethod, storage_path('app/temp').'/'.$fileName, $bookId, $bookName, $chapterName);
            } elseif ($importMethod === 'text') {
                // text
                $this->importService->importText($userId, $userUuid, $chunkSize, $textProcessingMethod, $importText, $bookId, $bookName, $chapterName);
            } elseif ($importMethod === 'subtitle') {
                // text
                $this->importService->importSubtitles($userId, $userUuid, $chunkSize, $textProcessingMethod, $importSubtitles, $bookId, $bookName, $chapterName);
            }
        } catch (\Exception $e) {
            // delete temp file
            if (isset($importFile)) {
                $this->tempFileService->deleteTempFile($fileName);
            }

            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(502, 'Không xử lý được nội dung. Kiểm tra nguồn và thử lại.');
        }

        // delete temp file
        if (isset($importFile)) {
            $this->tempFileService->deleteTempFile($fileName);
        }

        return response()->json('The text has been imported successfully.', 200);
    }

    public function getYoutubeSubtitles(GetYoutubeSubtitlesRequest $request)
    {
        $url = $request->post('url');

        try {
            $subtitleList = $this->importService->getYoutubeSubtitles($url);
        } catch (\Exception $e) {
            abort(502, 'Không lấy được phụ đề từ nguồn này.');
        }

        return response()->json($subtitleList, 200);
    }

    public function getSubtitleFileContent(GetSubtitleFileContentRequest $request)
    {
        $subtitleFile = $request->file('subtitleFile');
        $userId = Auth::user()->id;

        // move file to temp folder
        try {
            $fileName = $this->tempFileService->moveFileToTempFolder($userId, $subtitleFile);

            // get subtitle content
            $subtitleContent = $this->importService->getSubtitleFileContent(storage_path('app/temp').'/'.$fileName);
        } catch (\Exception $e) {
            if (isset($fileName)) {
                $this->tempFileService->deleteTempFile($fileName);
            }
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(502, 'Không xử lý được nội dung. Kiểm tra nguồn và thử lại.');
        }

        // delete temp file
        $this->tempFileService->deleteTempFile($fileName);

        return response()->json($subtitleContent, 200);
    }

    public function getWebsiteText(GetWebsiteTextRequest $request)
    {
        $url = $request->post('url');

        try {
            $websiteText = $this->importService->getWebsiteText($url);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(502, 'Không xử lý được nội dung. Kiểm tra nguồn và thử lại.');
        }

        return response()->json($websiteText, 200);
    }
}
