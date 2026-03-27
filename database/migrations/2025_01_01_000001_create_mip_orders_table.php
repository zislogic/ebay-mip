<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mip_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ebay_credential_id')
                ->constrained('ebay_credentials')
                ->cascadeOnDelete();
            $table->string('order_id')->index();
            $table->string('buyer_user_id');
            $table->string('buyer_email')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('order_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('ship_to_name')->nullable();
            $table->string('ship_to_phone')->nullable();
            $table->string('ship_to_street1')->nullable();
            $table->string('ship_to_street2')->nullable();
            $table->string('ship_to_city')->nullable();
            $table->string('ship_to_state')->nullable();
            $table->string('ship_to_zip')->nullable();
            $table->string('ship_to_country')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['ebay_credential_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mip_orders');
    }
};
