<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('job_key');
            $table->string('source', 32);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->string('triggered_by_name')->nullable();
            $table->string('status', 16);
            $table->unsignedInteger('success_count')->nullable();
            $table->unsignedInteger('failed_count')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['job_key', 'started_at']);
            $table->index(['started_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
