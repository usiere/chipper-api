<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing post_id data to polymorphic columns
        DB::table('favorites')->whereNotNull('post_id')->update([
            'favoritable_id' => DB::raw('post_id'),
            'favoritable_type' => 'App\\Models\\Post',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset polymorphic data back to post_id
        DB::table('favorites')
            ->where('favoritable_type', 'App\\Models\\Post')
            ->update([
                'post_id' => DB::raw('favoritable_id'),
                'favoritable_id' => null,
                'favoritable_type' => null,
            ]);
    }
};
