<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->string('type', 16)->default('text')->after('client_id');
            // Служебное сообщение о звонке: исход и длительность берём из самого звонка
            $table->foreignId('call_id')->nullable()->after('type')->constrained('calls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('call_id');
            $table->dropColumn('type');
        });
    }
};
