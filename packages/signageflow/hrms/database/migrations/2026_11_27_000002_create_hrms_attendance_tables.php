<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrms_employee_shifts')) {
            Schema::create('hrms_employee_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('hrms_shifts')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_week_off_override')->default(false);
                $table->timestamps();

                $table->index(['employee_id', 'effective_from', 'effective_to'], 'hrms_emp_shift_range_idx');
            });
        }

        if (! Schema::hasTable('hrms_kiosk_devices')) {
            Schema::create('hrms_kiosk_devices', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->foreignId('work_location_id')->nullable()->constrained('hrms_work_locations')->nullOnDelete();
                $table->string('device_token_hash', 128)->unique();
                $table->boolean('require_snapshot')->default(false);
                $table->boolean('allow_pin')->default(true);
                $table->boolean('allow_qr')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_attendance_punches')) {
            Schema::create('hrms_attendance_punches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->dateTime('punched_at');
                $table->date('punch_date');
                $table->enum('direction', ['in', 'out', 'break_start', 'break_end', 'auto'])->default('auto');
                $table->enum('channel', ['mobile', 'kiosk', 'biometric', 'manual'])->default('mobile');
                $table->foreignId('work_location_id')->nullable()->constrained('hrms_work_locations')->nullOnDelete();
                $table->foreignId('kiosk_device_id')->nullable()->constrained('hrms_kiosk_devices')->nullOnDelete();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('distance_meters', 10, 2)->nullable();
                $table->boolean('is_out_of_geofence')->default(false);
                $table->string('selfie_path')->nullable();
                $table->json('device_meta')->nullable();
                $table->json('raw_payload')->nullable();
                $table->enum('status', ['valid', 'flagged', 'rejected'])->default('valid');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'punch_date'], 'hrms_punch_emp_date_idx');
                $table->index(['punch_date', 'channel'], 'hrms_punch_date_channel_idx');
            });
        }

        if (! Schema::hasTable('hrms_attendance_days')) {
            Schema::create('hrms_attendance_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->foreignId('shift_id')->nullable()->constrained('hrms_shifts')->nullOnDelete();
                $table->dateTime('first_in_at')->nullable();
                $table->dateTime('last_out_at')->nullable();
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('break_minutes')->default(0);
                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_out_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->unsignedInteger('approved_overtime_minutes')->default(0);
                $table->enum('status', ['present', 'absent', 'half_day', 'on_leave', 'fwa', 'holiday', 'week_off'])->default('absent');
                $table->string('leave_code', 30)->nullable();
                $table->boolean('is_late')->default(false);
                $table->boolean('is_early_out')->default(false);
                $table->boolean('is_geofence_flagged')->default(false);
                $table->boolean('is_locked_by_payroll')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'attendance_date'], 'hrms_att_day_emp_date_unique');
                $table->index(['attendance_date', 'status'], 'hrms_att_day_date_status_idx');
            });
        }

        if (! Schema::hasTable('hrms_attendance_requests')) {
            Schema::create('hrms_attendance_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->enum('request_type', ['missed_in', 'missed_out', 'time_correction', 'geofence_override'])->default('time_correction');
                $table->dateTime('requested_in_at')->nullable();
                $table->dateTime('requested_out_at')->nullable();
                $table->text('reason');
                $table->string('attachment_path')->nullable();
                $table->enum('approval_status', ['pending_l1', 'pending_l2', 'approved', 'rejected', 'cancelled'])->default('pending_l1');
                $table->unsignedTinyInteger('current_level')->default(1);
                $table->foreignId('current_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
                $table->foreignId('l1_acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('l1_acted_at')->nullable();
                $table->foreignId('l2_acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('l2_acted_at')->nullable();
                $table->boolean('acted_via_backup')->default(false);
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->index(['approval_status', 'current_approver_id'], 'hrms_att_req_approver_idx');
            });
        }

        if (! Schema::hasTable('hrms_overtime_approvals')) {
            Schema::create('hrms_overtime_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_day_id')->constrained('hrms_attendance_days')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->unsignedInteger('computed_ot_minutes');
                $table->unsignedInteger('approved_ot_minutes')->default(0);
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acted_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->unique(['attendance_day_id'], 'hrms_ot_att_day_unique');
            });
        }

        if (! Schema::hasTable('hrms_break_adjustments')) {
            Schema::create('hrms_break_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_day_id')->constrained('hrms_attendance_days')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
                $table->unsignedInteger('previous_break_minutes')->default(0);
                $table->unsignedInteger('adjusted_break_minutes');
                $table->text('reason');
                $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_attendance_allowances')) {
            Schema::create('hrms_attendance_allowances', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->decimal('amount', 12, 2);
                $table->unsignedSmallInteger('max_absences_allowed')->default(0);
                $table->unsignedSmallInteger('max_lates_allowed')->default(2);
                $table->unsignedSmallInteger('min_present_days')->default(22);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_attendance_allowance_entries')) {
            Schema::create('hrms_attendance_allowance_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_allowance_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->decimal('computed_amount', 12, 2);
                $table->boolean('is_eligible')->default(true);
                $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();

                $table->foreign('attendance_allowance_id', 'hrms_att_allow_ent_rule_fk')
                    ->references('id')->on('hrms_attendance_allowances')->cascadeOnDelete();
                $table->foreign('employee_id', 'hrms_att_allow_ent_emp_fk')
                    ->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->foreign('approved_by', 'hrms_att_allow_ent_appr_fk')
                    ->references('id')->on('users')->nullOnDelete();

                $table->unique(['attendance_allowance_id', 'employee_id', 'period_year', 'period_month'], 'hrms_att_allow_emp_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_attendance_allowance_entries');
        Schema::dropIfExists('hrms_attendance_allowances');
        Schema::dropIfExists('hrms_break_adjustments');
        Schema::dropIfExists('hrms_overtime_approvals');
        Schema::dropIfExists('hrms_attendance_requests');
        Schema::dropIfExists('hrms_attendance_days');
        Schema::dropIfExists('hrms_attendance_punches');
        Schema::dropIfExists('hrms_kiosk_devices');
        Schema::dropIfExists('hrms_employee_shifts');
    }
};
