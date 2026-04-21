<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            $table->string('product_key', 120)->nullable();
            $table->string('placement', 80)->nullable();
            $table->string('color_code', 40)->nullable();

            $table->string('name', 255);
            $table->string('source', 30)->default('upload');
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();

            $table->string('disk', 80)->default('public');
            $table->string('path', 1024);
            $table->text('url');

            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'product_key', 'placement', 'color_code'], 'artworks_shop_context_idx');
            $table->index(['shop_id', 'mime_type'], 'artworks_shop_mime_idx');
            $table->index(['shop_id', 'source'], 'artworks_shop_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
