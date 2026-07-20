<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Order::with('items.product')->where('user_id', $request->user()->id);

            // Admins can see everyone's orders, optionally filtered to one user
            if ($request->user()->role === 'admin') {
                $query = Order::with(['items.product', 'user']);

                if ($request->filled('user_id')) {
                    $query->where('user_id', $request->user_id);
                }
            }

            return response()->json([
                'status' => true,
                'data' => $query->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->integer('per_page', 15)),
            ]);
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
            $order = Order::findOrFail($id);
            $this->authorizeOwner($request, $order);

            return response()->json([
                'status' => true,
                'data' => $order->load(['items.product', 'user']),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Order not found',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Place an order.
     * Body: { payment, delivery, items: [{ product_id, quantity }] }
     *
     * Mirrors Section 04 of the schema doc:
     *  1. create the order row
     *  2. create one order_items row per product
     *  3. compute total as a snapshot
     *  4. lock stock by decrementing inventory
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment' => 'required|string',
                'delivery' => 'required|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $order = DB::transaction(function () use ($validated, $request) {
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'date' => now()->toDateString(),
                    'total' => 0,
                    'payment' => $validated['payment'],
                    'delivery' => $validated['delivery'],
                    'status' => 'pending',
                ]);

                $total = 0;

                foreach ($validated['items'] as $item) {
                    $inventory = Inventory::where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($inventory->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Not enough stock for product_id {$item['product_id']}.",
                        ]);
                    }

                    $product = $inventory->product;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $product->price,
                    ]);

                    $inventory->decrement('stock', $item['quantity']);

                    $total += $product->price * $item['quantity'];
                }

                $order->update(['total' => $total, 'status' => 'confirmed']);

                return $order;
            });

            return response()->json([
                'status' => true,
                'data' => $order->load('items.product'),
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

    // Update status by ID
    public function updateStatus(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
            ]);

            $order->update($validated);

            return response()->json([
                'status' => true,
                'data' => $order,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'error' => 'Order not found',
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

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless(
            $request->user()->role === 'admin' || $order->user_id === $request->user()->id,
            403
        );
    }
}