<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Inventory::with('product')
                ->whereHas('product', function ($q) {
                    $q->whereNull('deleted_at');
                });

            if ($request->boolean('low_stock')) {
                $query->whereColumn('stock', '<=', 'min_threshold');
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

    public function show($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);

            return response()->json([
                'status' => true,
                'data' => $inventory->load('product'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Inventory record not found',
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
        $inventory = Inventory::findOrFail($id);

        $validated = $request->validate([
            'stock' => 'sometimes|required|integer|min:0',
            'incoming' => 'sometimes|integer|min:0',
            'min_threshold' => 'sometimes|integer|min:0',
            // status removed — controlled only via ProductController now
        ]);

        $inventory->update($validated);

        return response()->json([
            'status' => true,
            'data' => $inventory,
        ]);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'status' => false,
            'error' => 'Inventory record not found',
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

    // Convenience endpoint: move incoming stock into on-hand stock
    public function restock($id)
    {
        try {
            $inventory = Inventory::findOrFail($id);

            $inventory->stock += $inventory->incoming;
            $inventory->incoming = 0;
            $inventory->save();

            return response()->json([
                'status' => true,
                'data' => $inventory,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Inventory record not found',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
