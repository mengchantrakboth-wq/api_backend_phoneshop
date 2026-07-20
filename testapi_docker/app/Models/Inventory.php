<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'sku', 'stock', 'incoming', 'min_threshold', 'status'];

    // Inventory model
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }



    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_threshold;
    }
}
