<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Дотягиваем базовые колонки wishes, если их нет
        Schema::table('wishes', function (Blueprint $table) {
            if (!Schema::hasColumn('wishes', 'user_id')) {
                $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('wishes', 'title')) {
                $table->string('title')->default('');
            }
            if (!Schema::hasColumn('wishes', 'comment')) {
                $table->text('comment')->nullable();
            }
            if (!Schema::hasColumn('wishes', 'price')) {
                $table->decimal('price', 20, 2)->nullable();
            }
            if (!Schema::hasColumn('wishes', 'link')) {
                $table->string('link')->nullable();
            }
            if (!Schema::hasColumn('wishes', 'image_path')) {
                $table->string('image_path')->nullable();
            }
        });

        if (!Schema::hasColumn('wishes', 'wishlist_id')) {
            Schema::table('wishes', function (Blueprint $table) {
                $table->foreignId('wishlist_id')->nullable()->after('user_id')
                    ->constrained()->cascadeOnDelete();
            });

            // бэкфилл: у кого уже есть желания без списка — заводим дефолтный вишлист
            DB::table('users')->whereIn('id', DB::table('wishes')->whereNull('wishlist_id')->distinct()->pluck('user_id'))
                ->get()->each(function ($user) {
                    $id = DB::table('wishlists')->insertGetId([
                        'user_id'    => $user->id,
                        'title'      => 'Основной вишлист',
                        'slug'       => 'main',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('wishes')->where('user_id', $user->id)->whereNull('wishlist_id')->update(['wishlist_id' => $id]);
                });

            Schema::table('wishes', function (Blueprint $table) {
                $table->foreignId('wishlist_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            if (Schema::hasColumn('wishes', 'wishlist_id')) {
                $table->dropConstrainedForeignId('wishlist_id');
            }
        });
    }
};