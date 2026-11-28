<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrms_companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('gstin', 30)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('tan', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('hrms_companies')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_designations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_extra_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->unsignedSmallInteger('grace_in_minutes')->default(15);
            $table->unsignedSmallInteger('grace_out_minutes')->default(10);
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            $table->unsignedSmallInteger('full_day_minutes')->default(480);
            $table->boolean('ot_eligible')->default(true);
            $table->unsignedSmallInteger('ot_min_threshold_minutes')->default(30);
            $table->decimal('ot_multiplier', 5, 2)->default(1.50);
            $table->json('week_off_days')->nullable(); // e.g. [0] for Sunday
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_work_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('host_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(150);
            $table->enum('geofence_mode', ['strict', 'flag', 'disabled'])->default('flag');
            $table->boolean('require_selfie')->default(false);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('emp_code', 40)->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->date('date_of_joining');
            $table->date('date_of_exit')->nullable();
            $table->enum('employment_status', ['active', 'probation', 'notice', 'resigned', 'terminated'])->default('active');
            $table->enum('employment_type', ['permanent', 'contract', 'intern', 'daily_wage'])->default('permanent');

            // Org hierarchy
            $table->foreignId('company_id')->nullable()->constrained('hrms_companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('hrms_sections')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('hrms_designations')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('hrms_categories')->nullOnDelete();
            $table->foreignId('extra_classification_id')->nullable()->constrained('hrms_extra_classifications')->nullOnDelete();

            // Reporting & Approvals (L1 / L2 / Backup Officer)
            $table->foreignId('l1_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('l2_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('backup_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();

            // Attendance & Kiosk
            $table->foreignId('default_shift_id')->nullable()->constrained('hrms_shifts')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('hrms_work_locations')->nullOnDelete();
            $table->enum('geofence_mode', ['inherit', 'strict', 'flag', 'bypass'])->default('inherit');
            $table->boolean('selfie_required')->nullable();
            $table->string('kiosk_pin_hash')->nullable();
            $table->string('qr_badge_token', 64)->nullable()->unique();
            $table->boolean('mobile_attendance_enabled')->default(true);

            // Contact, Bank & India Statutory
            $table->string('official_email')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('emergency_contact', 60)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhaar_last4', 4)->nullable();
            $table->string('uan_number', 30)->nullable();
            $table->string('esic_number', 30)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 40)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('pt_state_code', 10)->default('MH');
            $table->boolean('epf_enabled')->default(true);
            $table->boolean('esic_enabled')->default(false);
            $table->boolean('pt_enabled')->default(true);
            $table->boolean('lwf_enabled')->default(false);
            $table->enum('tax_regime', ['new', 'old'])->default('new');

            $table->timestamps();

            $table->index(['employment_status', 'department_id'], 'hrms_emp_status_dept_idx');
            $table->index(['l1_approver_id'], 'hrms_emp_l1_idx');
        });

        Schema::create('hrms_employee_work_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('work_location_id')->constrained('hrms_work_locations')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'work_location_id'], 'hrms_emp_wloc_unique');
        });

        Schema::create('hrms_approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('backup_employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->enum('module', ['all', 'attendance', 'leave', 'payroll'])->default('all');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['delegator_employee_id', 'is_active', 'start_date', 'end_date'], 'hrms_deleg_active_idx');
        });

        Schema::create('hrms_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('name');
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->string('subject_template');
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_employee_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->enum('channel', ['email', 'push', 'in_app']);
            $table->enum('status', ['sent', 'failed', 'skipped'])->default('sent');
            $table->string('subject')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_key', 'channel', 'created_at'], 'hrms_notif_log_idx');
        });

        Schema::create('hrms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general');
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_settings');
        Schema::dropIfExists('hrms_notification_logs');
        Schema::dropIfExists('hrms_notification_templates');
        Schema::dropIfExists('hrms_approval_delegations');
        Schema::dropIfExists('hrms_employee_work_locations');
        Schema::dropIfExists('hrms_employees');
        Schema::dropIfExists('hrms_work_locations');
        Schema::dropIfExists('hrms_shifts');
        Schema::dropIfExists('hrms_extra_classifications');
        Schema::dropIfExists('hrms_categories');
        Schema::dropIfExists('hrms_designations');
        Schema::dropIfExists('hrms_sections');
        Schema::dropIfExists('hrms_departments');
        Schema::dropIfExists('hrms_companies');
    }
};
