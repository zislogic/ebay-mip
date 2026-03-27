<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mip_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mip_order_id')
                ->constrained('mip_orders')
                ->cascadeOnDelete();
            $table->string('line_item_id');
            $table->string('item_id');
            $table->string('sku')->nullable()->index();
            $table->string('title');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->string('logistics_status')->nullable();
            $table->string('fulfillment_status')->nullable()->index();
            $table->string('tracking_number')->nullable();
            $table->string('shipping_carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['mip_order_id', 'line_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mip_order_lines');
    }
};
