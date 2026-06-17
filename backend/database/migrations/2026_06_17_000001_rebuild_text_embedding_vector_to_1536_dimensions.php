<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('DROP INDEX IF EXISTS products_text_embedding_hnsw');
        DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS text_embedding_vector');
        DB::statement('ALTER TABLE products ADD COLUMN text_embedding_vector vector(1536)');
        DB::statement('CREATE INDEX products_text_embedding_hnsw ON products USING hnsw (text_embedding_vector vector_cosine_ops)');

        DB::table('products')->update([
            'text_embedding' => null,
            'text_embedding_model' => null,
            'embeddings_indexed_at' => null,
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS products_text_embedding_hnsw');
        DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS text_embedding_vector');
        DB::statement('ALTER TABLE products ADD COLUMN text_embedding_vector vector(128)');
        DB::statement('CREATE INDEX products_text_embedding_hnsw ON products USING hnsw (text_embedding_vector vector_cosine_ops)');

        DB::table('products')->update([
            'text_embedding' => null,
            'text_embedding_model' => null,
            'embeddings_indexed_at' => null,
        ]);
    }
};
