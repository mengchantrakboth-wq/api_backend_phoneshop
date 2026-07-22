<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{   
  
   public function index(Request $request)
{
    try {
        $query = Category::withCount('products');

        if ($request->boolean('active_only')) {
            $query->where('status', 'active');
        }

        return response()->json([
            'status' => true,
            'data' => $query->get(),
        ]);
    } catch (\Throwable $th) {
        return response()->json([
            'status' => false,
            'error' => $th->getMessage(),
        ], 500);
    }
}

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'status' => 'in:active,inactive',
            ]);

            $category = Category::create($validated);

            return response()->json([
                'status' => true,
                'data' => $category,
            ], 201);

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
            $category = Category::findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $category->load('products'),
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'status' => 'in:active,inactive',
            ]);

            $category->update($validated);

            return response()->json([
                'status' => true,
                'data' => $category,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);

            $category->delete();

            return response()->json([
                'status' => true,
                'message' => 'Category deleted',
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}