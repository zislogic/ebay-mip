<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mip_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ebay_credential_id')
                ->constrained('ebay_credentials')
                ->cascadeOnDelete();
            $table->string('feed_type', 50)->index();
            $table->string('remote_path')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->string('status', 50)->default('pending')->index();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mip_feeds');
    }
};
