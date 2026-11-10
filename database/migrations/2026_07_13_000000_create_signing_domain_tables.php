<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_id', 24)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->string('company_name');
            $table->string('vat_number', 20);
            $table->string('signer_name');
            $table->string('signer_role');
            $table->string('signer_email');
            $table->string('signer_pec')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 32)->default('draft')->index();
            $table->string('source_path');
            $table->char('source_hash', 64);
            $table->unsignedBigInteger('source_size');
            $table->unsignedInteger('source_pages')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('client_signed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->timestampTz('retention_until')->nullable()->index();
            $table->string('final_pdf_path')->nullable();
            $table->char('final_pdf_hash', 64)->nullable();
            $table->string('manifest_path')->nullable();
            $table->text('manifest_signature')->nullable();
            $table->string('package_path')->nullable();
            $table->timestampsTz();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('session_hash', 64)->nullable()->index();
            $table->timestampTz('expires_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_sent_at')->nullable();
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->timestampsTz();
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->ipAddress('requested_ip')->nullable();
            $table->timestampsTz();
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('party', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role');
            $table->string('email');
            $table->json('consents');
            $table->foreignId('otp_challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('signed_at');
            $table->timestampsTz();
            $table->unique(['contract_id', 'party']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('actor_type', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event_type', 80)->index();
            $table->json('payload');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64)->unique();
            $table->timestampTz('created_at')->index();
        });

        Schema::create('mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('email');
            $table->string('kind', 32);
            $table->string('recipient');
            $table->string('message_id')->nullable()->index();
            $table->string('status', 24)->default('queued');
            $table->json('metadata')->nullable();
            $table->string('raw_path')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('pec_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_type', 32);
            $table->string('message_id')->nullable()->index();
            $table->string('raw_path');
            $table->char('content_hash', 64);
            $table->timestampTz('received_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pec_receipts');
        Schema::dropIfExists('mail_deliveries');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('contracts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'recovery_codes', 'two_factor_confirmed_at', 'last_login_at', 'is_active']);
        });
    }
};
