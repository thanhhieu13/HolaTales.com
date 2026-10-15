<?php

namespace App\Http\Controllers;

use App\Http\Requests\Review\GetReviewItemsRequest;
// services
use App\Http\Requests\Review\UpdateReviewGoalRequest;
use App\Services\FlashcardReviewService;
// request classes
use App\Services\GoalService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ReviewController extends Controller
{
    private $reviewService;

    private $goalService;

    public function __construct(ReviewService $reviewService, GoalService $goalService)
    {
        $this->reviewService = $reviewService;
        $this->goalService = $goalService;
    }

    public function getReviewItems(GetReviewItemsRequest $request)
    {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;
        $practiceMode = $request->post('practiceMode');
        $chapterId = $request->post('chapterId');
        $bookId = $request->post('bookId');
        $languagesWithoutSpaces = config('linguacafe.languages.languages_without_spaces');

        try {
            $reviews = $this->reviewService->getReviewItems($userId, $language, $bookId, $chapterId, $practiceMode, $languagesWithoutSpaces);
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được lượt ôn.');
        }

        $reviewData = new \stdClass;
        $reviewData->reviews = $reviews;
        $reviewData->language = $language;
        $reviewData->target = Auth::user()->translation_language;
        $reviewData->languageSpaces = ! in_array($language, $languagesWithoutSpaces, true);

        return response()->json($reviewData, 200);
    }

    public function submit(Request $request, FlashcardReviewService $service)
    {
        $data = $request->validate([
            'request_id' => 'required|uuid', 'kind' => 'required|in:word,phrase',
            'item_id' => 'required|integer|min:1', 'source' => 'required|string|max:32',
            'target' => 'required|string|max:8', 'grade' => 'required|in:again,hard,good,easy',
            'expected_stage' => 'required|integer|between:-7,-1', 'expected_relearning' => 'required|boolean',
        ]);

        return response()->json($service->submit($request->user(), $data));
    }

    public function updateReadWordsGoal(UpdateReviewGoalRequest $request)
    {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;
        $readWords = $request->post('readWords');

        try {
            $this->goalService->updateGoalAchievement($userId, $language, 'read_words', $readWords);
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được lượt ôn.');
        }

        return response()->json('Review goal has been updated successfully.', 200);
    }
}
