<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'wish_id')) {
                $table->foreignId('wish_id')->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'token')) {
                $table->string('token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'token')) {
                $table->dropColumn('token');
            }
        });
    }
};