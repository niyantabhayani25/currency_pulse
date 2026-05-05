<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 15, 6)->nullable();

            // Composite unique: one rate per date per report
            // This index also serves as the primary lookup (report_id is leftmost prefix)
            $table->unique(['report_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_results');
    }
};
