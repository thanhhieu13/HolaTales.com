<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\AuthenticateUserRequest;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdatePasswordRequest;
use App\Http\Requests\Users\UpdateUserRequest;
// request classes
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;

    }

    public function isUserPasswordChanged()
    {
        $passwordChanged = Auth::user()->password_changed;

        return response()->json($passwordChanged, 200);
    }

    public function getUsers()
    {
        $userId = Auth::user()->id;

        try {
            $users = $this->userService->getUsers($userId);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json($users, 200);
    }

    public function showLoginForm()
    {
        $userCount = User::count();
        $theme = $_COOKIE['theme'] ?? 'light';

        if (Auth::check()) {
            return redirect()->intended('/learn');
        }

        return view('auth.login', [
            'userCount' => $userCount,
            'userUuid' => '',
            'theme' => $theme,
        ]);
    }

    public function authenticateUser(AuthenticateUserRequest $request)
    {
        $email = $request->post('email');
        $password = $request->post('password');

        if (Auth::attempt([
            'email' => $email,
            'password' => $password,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();
            // Keep other signed-in devices active for individual SaaS accounts.

            return response()->json('User has been logged in successfully.', 200);
        } else {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = Auth::user();
        $password = $request->post('password');

        try {
            $this->userService->updatePassword($user, $password);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('Password has been updated successfully.', 200);
    }

    public function createUser(CreateUserRequest $request)
    {
        $userCount = User::count();
        $name = $request->post('name');
        $email = $request->post('email');
        $password = $request->post('password');
        $isAdmin = $request->post('isAdmin');
        $passwordChanged = false;

        // If this is the first user, it can be created without any authorization.
        if (! Auth::check() || ! Auth::user()->is_admin) {
            abort(403, 'Only administrators can create managed accounts.');
        }

        try {
            $this->userService->createUser($name, $email, $password, $isAdmin, $passwordChanged);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('User has been created successfully.', 200);
    }

    public function updateUser(UpdateUserRequest $request)
    {
        $userId = $request->post('userId');
        $name = $request->post('name');
        $email = $request->post('email');
        $isAdmin = $request->post('isAdmin');

        try {
            $this->userService->updateUser($userId, $name, $email, $isAdmin);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('User has been updated successfully.', 200);
    }

    public function deleteUserLanguageData($language)
    {
        $userId = Auth::user()->id;

        try {
            $this->userService->deleteUserLanguageData($userId, $language);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return response()->json('User has been deleted successfully.', 200);
    }
}
