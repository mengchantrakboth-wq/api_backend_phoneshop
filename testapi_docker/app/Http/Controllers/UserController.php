<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::query();

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            return response()->json([
                'status' => true,
                'data' => $query->latest()->paginate($request->integer('per_page', 15)),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $user,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'User not found',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}