<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('text_embedding')->nullable();
            $table->json('image_embedding')->nullable();
            $table->string('text_embedding_model')->nullable();
            $table->string('image_embedding_model')->nullable();
            $table->timestamp('embeddings_indexed_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            $textDimensions = config('services.search.text_dimensions');
            $imageDimensions = config('services.search.image_dimensions');
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement("ALTER TABLE products ADD COLUMN text_embedding_vector vector($textDimensions)");
            DB::statement("ALTER TABLE products ADD COLUMN image_embedding_vector vector($imageDimensions)");
            DB::statement('CREATE INDEX products_text_embedding_hnsw ON products USING hnsw (text_embedding_vector vector_cosine_ops)');
            DB::statement('CREATE INDEX products_image_embedding_hnsw ON products USING hnsw (image_embedding_vector vector_cosine_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS products_text_embedding_hnsw');
            DB::statement('DROP INDEX IF EXISTS products_image_embedding_hnsw');
            DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS text_embedding_vector');
            DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS image_embedding_vector');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'text_embedding',
                'image_embedding',
                'text_embedding_model',
                'image_embedding_model',
                'embeddings_indexed_at',
            ]);
        });
    }
};
