<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_categories', function (Blueprint $table): void {
            $table->string('system_key', 32)->nullable();
            $table->unique(['account_id', 'system_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_categories', function (Blueprint $table): void {
            $table->dropUnique(['account_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }
};
