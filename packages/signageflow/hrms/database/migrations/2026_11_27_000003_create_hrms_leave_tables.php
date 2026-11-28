<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrms_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_paid')->default(true);
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('attachment_after_days')->default(0);
            $table->enum('accrual_frequency', ['monthly', 'quarterly', 'annual', 'none'])->default('monthly');
            $table->decimal('annual_quota', 6, 2)->default(12.00);
            $table->decimal('max_carry_forward', 6, 2)->default(0.00);
            $table->boolean('allow_encashment')->default(false);
            $table->enum('gender_restriction', ['all', 'female', 'male'])->default('all');
            $table->unsignedSmallInteger('min_tenure_days')->default(0);
            $table->json('applicable_categories')->nullable();
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_fwa_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->enum('mode', ['wfh', 'flexi_hours', 'remote_site'])->default('wfh');
            $table->unsignedSmallInteger('max_days_per_month')->default(4);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrms_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 15);
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('holiday_type', ['public_holiday', 'restricted_day', 'blocked_annual_leave', 'rest_day'])->default('public_holiday');
            $table->foreignId('company_id')->nullable()->constrained('hrms_companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hrms_departments')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('hrms_work_locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['financial_year', 'holiday_type', 'start_date'], 'hrms_holiday_fy_type_idx');
        });

        Schema::create('hrms_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hrms_leave_types')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->decimal('opening_balance', 7, 2)->default(0);
            $table->decimal('accrued', 7, 2)->default(0);
            $table->decimal('credited_adjustment', 7, 2)->default(0);
            $table->decimal('taken', 7, 2)->default(0);
            $table->decimal('pending', 7, 2)->default(0);
            $table->decimal('lapsed', 7, 2)->default(0);
            $table->decimal('encashed', 7, 2)->default(0);
            $table->decimal('closing_balance', 7, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'financial_year'], 'hrms_leave_bal_emp_type_fy_unique');
        });

        Schema::create('hrms_leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hrms_leave_types')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->enum('txn_type', ['opening', 'accrual', 'debit', 'reversal', 'adjustment', 'carry_forward', 'lapse', 'encashment', 'comp_off_credit']);
            $table->decimal('days', 7, 2);
            $table->decimal('balance_after', 7, 2);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'financial_year'], 'hrms_leave_txn_emp_fy_idx');
        });

        Schema::create('hrms_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hrms_leave_types')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('day_mode', ['full', 'first_half', 'second_half'])->default('full');
            $table->decimal('total_days', 5, 2);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('apply_mode', ['self', 'manager', 'super_admin', 'batch'])->default('self');
            $table->enum('approval_status', ['pending_l1', 'pending_l2', 'approved', 'rejected', 'cancelled'])->default('pending_l1');
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->foreignId('current_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('l1_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->string('l1_status', 20)->nullable();
            $table->timestamp('l1_acted_at')->nullable();
            $table->foreignId('l2_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->string('l2_status', 20)->nullable();
            $table->timestamp('l2_acted_at')->nullable();
            $table->boolean('acted_via_backup')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['approval_status', 'current_approver_id'], 'hrms_leave_req_approver_idx');
            $table->index(['employee_id', 'start_date', 'end_date'], 'hrms_leave_req_emp_dates_idx');
        });

        Schema::create('hrms_fwa_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->foreignId('fwa_policy_id')->constrained('hrms_fwa_policies')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->enum('approval_status', ['pending_l1', 'pending_l2', 'approved', 'rejected', 'cancelled'])->default('pending_l1');
            $table->foreignId('current_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('hrms_permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->date('permission_date');
            $table->time('from_time');
            $table->time('to_time');
            $table->unsignedSmallInteger('minutes');
            $table->text('reason');
            $table->enum('approval_status', ['pending_l1', 'approved', 'rejected', 'cancelled'])->default('pending_l1');
            $table->foreignId('current_approver_id')->nullable()->constrained('hrms_employees')->nullOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hrms_comp_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrms_employees')->cascadeOnDelete();
            $table->string('financial_year', 15);
            $table->date('worked_date');
            $table->decimal('days_earned', 4, 2)->default(1.00);
            $table->date('expires_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'used', 'expired', 'rejected'])->default('approved');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrms_leave_year_end_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_financial_year', 15);
            $table->string('to_financial_year', 15);
            $table->unsignedInteger('employees_processed')->default(0);
            $table->decimal('total_carried_forward', 10, 2)->default(0);
            $table->decimal('total_lapsed', 10, 2)->default(0);
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_leave_year_end_runs');
        Schema::dropIfExists('hrms_comp_offs');
        Schema::dropIfExists('hrms_permission_requests');
        Schema::dropIfExists('hrms_fwa_requests');
        Schema::dropIfExists('hrms_leave_requests');
        Schema::dropIfExists('hrms_leave_transactions');
        Schema::dropIfExists('hrms_leave_balances');
        Schema::dropIfExists('hrms_holidays');
        Schema::dropIfExists('hrms_fwa_policies');
        Schema::dropIfExists('hrms_leave_types');
    }
};
