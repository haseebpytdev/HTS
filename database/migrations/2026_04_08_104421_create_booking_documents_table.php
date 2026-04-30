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
        Schema::create('booking_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('superseded_by_document_id')->nullable()->constrained('booking_documents')->nullOnDelete();

            $table->string('document_type')->nullable();
            $table->uuid('document_group_uuid')->nullable()->index();
            $table->unsignedInteger('version_number')->default(1);

            $table->string('validation_status')->default('pending')->index();
            $table->string('virus_scan_status')->default('pending_scan')->index();
            $table->timestamp('virus_scanned_at')->nullable();
            $table->text('virus_scan_note')->nullable();

            $table->string('original_name')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable()->index();

            $table->boolean('is_customer_visible')->default(false);
            $table->text('notes')->nullable();
            $table->text('validation_note')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_documents');
    }
};
