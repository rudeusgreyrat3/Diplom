<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            // true — это особый вишлист «Мои хотелки», куда переезжают
            // незабронированные желания из прошедших/удалённых вишлистов
            $table->boolean('is_hotelki')->default(false)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('is_hotelki');
        });
    }
};
