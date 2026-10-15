<?php

namespace App\Http\Controllers;

use App\Http\Requests\Books\CreateBookRequest;
// request classes
use App\Http\Requests\Books\DeleteBookRequest;
use App\Http\Requests\Books\GetBookWordCountsRequest;
use App\Http\Requests\Books\UpdateBookRequest;
use App\Services\BookService;
// services
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BookController extends Controller
{
    private $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }

    public function getBooks()
    {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;

        $books = $this->bookService->getBooks($userId, $language);

        return response()->json($books, 200);
    }

    public function getBookWordCounts($bookId, GetBookWordCountsRequest $request)
    {
        $userId = Auth::user()->id;

        try {
            $wordCounts = $this->bookService->getBookWordCounts($userId, $bookId);
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        }

        return response()->json($wordCounts, 200);
    }

    public function createBook(CreateBookRequest $request)
    {
        $userId = Auth::user()->id;
        $language = Auth::user()->selected_language;
        $bookName = $request->post('bookName');
        $bookCoverFile = $request->file('bookCover');

        try {
            $this->bookService->createBook($userId, $language, $bookName, $bookCoverFile);
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        }

        return response()->json('Book has been successfully created.', 200);
    }

    public function updateBook(UpdateBookRequest $request)
    {
        $userId = Auth::user()->id;
        $bookId = $request->post('bookId');
        $bookName = $request->post('bookName');
        $bookCoverFile = $request->file('bookCover');

        try {
            $this->bookService->updateBook($userId, $bookId, $bookName, $bookCoverFile);
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        }

        return response()->json('Book has been successfully updated.', 200);
    }

    public function deleteBook(DeleteBookRequest $request)
    {
        $bookId = $request->post('bookId');
        $userId = Auth::user()->id;

        try {
            $this->bookService->deleteBook($userId, $bookId);
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            abort(500, 'Không xử lý được yêu cầu.');
        }

        return response()->json('Book has been successfully deleted.', 200);
    }
}
