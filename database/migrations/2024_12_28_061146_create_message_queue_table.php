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
        Schema::create('message_queue', function (Blueprint $table) {
            $table->id();
            $table->string('instance_name')->index();
            $table->string('type'); // broadcast|direct
            $table->longText('payload');
            $table->string('target_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_queue');
    }
};
