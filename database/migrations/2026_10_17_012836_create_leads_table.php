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
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->date('first_contact_date');
            $table->date('next_contact_date')->nullable();
            $table->decimal('potential_amount', 12, 2)->nullable();
            $table->decimal('charged_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign(['account_id', 'category_id'])->references(['account_id', 'id'])
                ->on('lead_categories')->cascadeOnDelete();
            $table->index(['account_id', 'id']);
            $table->index(['category_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
