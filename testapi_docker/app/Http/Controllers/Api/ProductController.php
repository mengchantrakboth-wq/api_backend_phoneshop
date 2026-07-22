<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
{
    try {
        $query = Product::with(['category', 'inventory']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if (!$request->boolean('is_admin')) {
            $query->whereHas('category', function ($q) {
                $q->where('status', 'active');
            });
        }

        return response()->json([
            'status' => true,
            'data' => $query->paginate($request->integer('per_page', 15)),
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
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'status' => 'in:active,inactive',
                'image' => 'nullable|image|max:4096',
                'sku' => 'required|string|unique:inventories,sku',
                'stock' => 'required|integer|min:0',
                'incoming' => 'sometimes|integer|min:0',
                'min_threshold' => 'sometimes|integer|min:0',
            ]);

            $imagePath = $request->hasFile('image')
                ? $request->file('image')->store('products', 'public')
                : null;

            $product = DB::transaction(function () use ($validated, $imagePath) {
                $product = Product::create([
                    'category_id' => $validated['category_id'],
                    'name' => $validated['name'],
                    'price' => $validated['price'],
                    'image' => $imagePath,
                    'status' => $validated['status'] ?? 'active',
                ]);

                $product->inventory()->create([
                    'sku' => $validated['sku'],
                    'stock' => $validated['stock'],
                    'incoming' => $validated['incoming'] ?? 0,
                    'min_threshold' => $validated['min_threshold'] ?? 0,
                ]);

                return $product;
            });

            return response()->json([
                'status' => true,
                'data' => $product->load('inventory'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'error' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

   public function show(Request $request, $id)
{
    try {
        $query = Product::query();

        if (!$request->boolean('is_admin')) {
            $query->whereHas('category', function ($q) {
                $q->where('status', 'active');
            });
        }

        $product = $query->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $product->load(['category', 'inventory']),
        ]);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'status' => false,
            'error' => 'Product not found',
        ], 404);
    } catch (\Throwable $th) {
        return response()->json([
            'status' => false,
            'error' => $th->getMessage(),
        ], 500);
    }
}

    // Update by ID
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name' => 'sometimes|required|string|max:255',
                'price' => 'sometimes|required|numeric|min:0',
                'status' => 'in:active,inactive',
                'image' => 'nullable|image|max:4096',
            ]);

            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            $product->update($validated);

            return response()->json([
                'status' => true,
                'data' => $product->load('inventory'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Product not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'error' => $e->errors(),
            ], 422);
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
            $product = Product::findOrFail($id);

            // Note: we no longer delete the image file here,
            // since the product isn't actually being removed —
            // just hidden. Keep the image in case it's restored.

            $product->delete(); // soft delete: sets deleted_at

            return response()->json([
                'status' => true,
                'message' => 'Product deleted',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Product not found',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function trashed(Request $request)
    {
        try {
            $query = Product::onlyTrashed()->with(['category', 'inventory']);

            return response()->json([
                'status' => true,
                'data' => $query->paginate($request->integer('per_page', 15)),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $product = Product::onlyTrashed()->findOrFail($id);
            $product->restore();

            return response()->json([
                'status' => true,
                'message' => 'Product restored',
                'data' => $product->load('inventory'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Product not found in trash',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function forceDelete($id)
    {
        try {
            $product = Product::onlyTrashed()->findOrFail($id);

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $product->forceDelete(); // hard delete — fails if order_items still reference it

            return response()->json([
                'status' => true,
                'message' => 'Product permanently deleted',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Product not found in trash',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
