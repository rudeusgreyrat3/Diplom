<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Жалобы на брони
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            // бронь может быть позже удалена — поэтому nullOnDelete и «снимок» данных ниже
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wish_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

            // на кого жалуются: аккаунт или гость (по cookie-токену)
            $table->foreignId('accused_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accused_token')->nullable()->index();
            $table->string('accused_name')->nullable();

            $table->string('wish_title')->nullable();
            $table->string('reason');               // код причины
            $table->text('comment')->nullable();    // пояснение от пожаловавшегося
            $table->string('status')->default('new'); // new | resolved | rejected
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Блокировки
        Schema::create('bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token')->nullable()->index(); // для гостей без аккаунта
            $table->string('display_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('until')->nullable();       // null = навсегда
            $table->timestamp('lifted_at')->nullable();   // снят админом досрочно
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Внутренние сообщения от администрации пользователям
        Schema::create('user_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_messages');
        Schema::dropIfExists('bans');
        Schema::dropIfExists('complaints');
    }
};
