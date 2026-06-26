<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('mime', 127);
            $table->unsignedInteger('size')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('admin users.id');
            $table->nullableMorphs('attachable');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_attachments');
    }
};
