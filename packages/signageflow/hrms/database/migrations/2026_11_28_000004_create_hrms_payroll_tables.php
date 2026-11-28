<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrms_salary_components')) {
            Schema::create('hrms_salary_components', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->enum('type', ['earning', 'deduction', 'employer_contribution'])->default('earning');
                $table->enum('calc_type', ['fixed', 'percent_of_basic', 'percent_of_gross', 'formula'])->default('fixed');
                $table->string('formula_or_value')->default('0');
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_pf_wage')->default(false);
                $table->boolean('is_esic_wage')->default(false);
                $table->boolean('is_pt_wage')->default(false);
                $table->boolean('is_lwf_wage')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(10);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_bank_templates')) {
            Schema::create('hrms_bank_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->enum('format', ['csv', 'xlsx'])->default('csv');
                $table->json('columns_mapping');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_pay_groups')) {
            Schema::create('hrms_pay_groups', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name');
                $table->enum('pay_frequency', ['monthly', 'weekly'])->default('monthly');
                $table->unsignedBigInteger('l1_approver_user_id')->nullable();
                $table->unsignedBigInteger('l2_approver_user_id')->nullable();
                $table->unsignedBigInteger('default_bank_template_id')->nullable();
                $table->json('tally_ledger_mapping')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('l1_approver_user_id', 'hrms_pg_l1_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('l2_approver_user_id', 'hrms_pg_l2_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('default_bank_template_id', 'hrms_pg_bank_tpl_fk')->references('id')->on('hrms_bank_templates')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hrms_statutory_configs')) {
            Schema::create('hrms_statutory_configs', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 15)->unique();
                $table->decimal('epf_employee_rate', 5, 2)->default(12.00);
                $table->decimal('epf_employer_rate', 5, 2)->default(12.00);
                $table->decimal('epf_wage_ceiling', 10, 2)->default(15000.00);
                $table->decimal('epf_admin_rate', 5, 2)->default(1.00);
                $table->decimal('esic_employee_rate', 5, 2)->default(0.75);
                $table->decimal('esic_employer_rate', 5, 2)->default(3.25);
                $table->decimal('esic_wage_ceiling', 10, 2)->default(21000.00);
                $table->json('pt_state_slabs')->nullable();
                $table->json('lwf_rules')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrms_salary_structures')) {
            Schema::create('hrms_salary_structures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('pay_group_id')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->decimal('monthly_ctc', 12, 2)->default(0);
                $table->decimal('monthly_gross', 12, 2)->default(0);
                $table->decimal('basic_salary', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('employee_id', 'hrms_sal_str_emp_fk')->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->foreign('pay_group_id', 'hrms_sal_str_pg_fk')->references('id')->on('hrms_pay_groups')->nullOnDelete();
                $table->index(['employee_id', 'is_active', 'effective_from'], 'hrms_sal_str_emp_act_idx');
            });
        }

        if (! Schema::hasTable('hrms_salary_structure_items')) {
            Schema::create('hrms_salary_structure_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_structure_id');
                $table->unsignedBigInteger('salary_component_id');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('formula_override')->nullable();
                $table->timestamps();

                $table->foreign('salary_structure_id', 'hrms_sal_item_str_fk')->references('id')->on('hrms_salary_structures')->cascadeOnDelete();
                $table->foreign('salary_component_id', 'hrms_sal_item_comp_fk')->references('id')->on('hrms_salary_components')->cascadeOnDelete();
                $table->unique(['salary_structure_id', 'salary_component_id'], 'hrms_sal_item_unique');
            });
        }

        if (! Schema::hasTable('hrms_salary_revisions')) {
            Schema::create('hrms_salary_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('previous_ctc', 12, 2)->default(0);
                $table->decimal('new_ctc', 12, 2)->default(0);
                $table->decimal('increment_percent', 6, 2)->default(0);
                $table->date('effective_from');
                $table->boolean('arrear_applicable')->default(false);
                $table->string('remarks')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('employee_id', 'hrms_sal_rev_emp_fk')->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->foreign('created_by', 'hrms_sal_rev_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hrms_loans')) {
            Schema::create('hrms_loans', function (Blueprint $table) {
                $table->id();
                $table->string('loan_number', 40)->unique();
                $table->unsignedBigInteger('employee_id');
                $table->enum('loan_type', ['salary_advance', 'personal_loan', 'emergency_loan'])->default('salary_advance');
                $table->decimal('principal_amount', 12, 2);
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->unsignedSmallInteger('tenure_months')->default(1);
                $table->decimal('emi_amount', 12, 2);
                $table->date('disbursed_date');
                $table->date('start_deduction_date');
                $table->decimal('outstanding_amount', 12, 2);
                $table->enum('status', ['active', 'paused', 'closed'])->default('active');
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('employee_id', 'hrms_loan_emp_fk')->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->foreign('created_by', 'hrms_loan_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hrms_loan_installments')) {
            Schema::create('hrms_loan_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('loan_id');
                $table->unsignedSmallInteger('installment_no');
                $table->date('due_date');
                $table->decimal('emi_amount', 12, 2);
                $table->decimal('principal_component', 12, 2)->default(0);
                $table->decimal('interest_component', 12, 2)->default(0);
                $table->enum('status', ['pending', 'deducted', 'skipped', 'paid'])->default('pending');
                $table->unsignedBigInteger('pay_run_item_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->foreign('loan_id', 'hrms_loan_inst_loan_fk')->references('id')->on('hrms_loans')->cascadeOnDelete();
                $table->index(['due_date', 'status'], 'hrms_loan_inst_due_idx');
            });
        }

        if (! Schema::hasTable('hrms_claims')) {
            Schema::create('hrms_claims', function (Blueprint $table) {
                $table->id();
                $table->string('claim_number', 40)->unique();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('host_expense_id')->nullable();
                $table->date('claim_date');
                $table->string('category', 80);
                $table->decimal('amount', 12, 2);
                $table->text('description');
                $table->string('receipt_path')->nullable();
                $table->enum('approval_status', ['pending_l1', 'pending_l2', 'approved', 'rejected', 'paid'])->default('pending_l1');
                $table->unsignedTinyInteger('current_level')->default(1);
                $table->unsignedBigInteger('current_approver_id')->nullable();
                $table->unsignedBigInteger('l1_acted_by')->nullable();
                $table->timestamp('l1_acted_at')->nullable();
                $table->unsignedBigInteger('l2_acted_by')->nullable();
                $table->timestamp('l2_acted_at')->nullable();
                $table->unsignedBigInteger('pay_run_item_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->foreign('employee_id', 'hrms_claim_emp_fk')->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->foreign('host_expense_id', 'hrms_claim_exp_fk')->references('id')->on('expenses')->nullOnDelete();
                $table->foreign('current_approver_id', 'hrms_claim_appr_fk')->references('id')->on('hrms_employees')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hrms_pay_runs')) {
            Schema::create('hrms_pay_runs', function (Blueprint $table) {
                $table->id();
                $table->string('run_code', 40)->unique();
                $table->unsignedBigInteger('pay_group_id');
                $table->enum('pay_frequency', ['monthly', 'weekly'])->default('monthly');
                $table->string('financial_year', 15);
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->unsignedTinyInteger('week_number')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->json('sync_options')->nullable();
                $table->enum('stage', ['initialized', 'pending_l1', 'pending_l2', 'approved', 'published'])->default('initialized');
                $table->decimal('total_gross', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('total_net', 14, 2)->default(0);
                $table->unsignedBigInteger('l1_approved_by')->nullable();
                $table->timestamp('l1_approved_at')->nullable();
                $table->unsignedBigInteger('l2_approved_by')->nullable();
                $table->timestamp('l2_approved_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->foreign('pay_group_id', 'hrms_pr_pg_fk')->references('id')->on('hrms_pay_groups')->cascadeOnDelete();
                $table->index(['period_year', 'period_month', 'stage'], 'hrms_pr_period_stage_idx');
            });
        }

        if (! Schema::hasTable('hrms_pay_run_items')) {
            Schema::create('hrms_pay_run_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pay_run_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('run_type', ['regular', 'arrear', 'fnf'])->default('regular');
                $table->boolean('is_initialized')->default(true);
                $table->decimal('payable_days', 5, 2)->default(0);
                $table->decimal('lop_days', 5, 2)->default(0);
                $table->decimal('ot_hours', 6, 2)->default(0);
                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_out_minutes')->default(0);
                $table->unsignedInteger('break_minutes')->default(0);
                $table->json('attendance_summary')->nullable();
                $table->json('earnings_json')->nullable();
                $table->json('deductions_json')->nullable();
                $table->json('employer_contributions_json')->nullable();
                $table->decimal('gross_earnings', 12, 2)->default(0);
                $table->decimal('epf_employee', 12, 2)->default(0);
                $table->decimal('epf_employer', 12, 2)->default(0);
                $table->decimal('esic_employee', 12, 2)->default(0);
                $table->decimal('esic_employer', 12, 2)->default(0);
                $table->decimal('pt_amount', 12, 2)->default(0);
                $table->decimal('lwf_amount', 12, 2)->default(0);
                $table->decimal('tds_amount', 12, 2)->default(0);
                $table->decimal('loan_emi_amount', 12, 2)->default(0);
                $table->decimal('claim_reimbursement_amount', 12, 2)->default(0);
                $table->decimal('attendance_allowance_amount', 12, 2)->default(0);
                $table->decimal('arrear_amount', 12, 2)->default(0);
                $table->decimal('fnf_settlement_amount', 12, 2)->default(0);
                $table->decimal('total_deductions', 12, 2)->default(0);
                $table->decimal('net_wages', 12, 2)->default(0);
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->string('payslip_pdf_path')->nullable();
                $table->boolean('is_published')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('pay_run_id', 'hrms_pri_pr_fk')->references('id')->on('hrms_pay_runs')->cascadeOnDelete();
                $table->foreign('employee_id', 'hrms_pri_emp_fk')->references('id')->on('hrms_employees')->cascadeOnDelete();
                $table->unique(['pay_run_id', 'employee_id', 'run_type'], 'hrms_pri_run_emp_type_unique');
            });
        }

        if (! Schema::hasTable('hrms_gl_batches')) {
            Schema::create('hrms_gl_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_code', 40)->unique();
                $table->unsignedBigInteger('pay_run_id');
                $table->date('voucher_date');
                $table->string('narration')->nullable();
                $table->json('entries_json');
                $table->decimal('total_debit', 14, 2)->default(0);
                $table->decimal('total_credit', 14, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('pay_run_id', 'hrms_gl_pr_fk')->references('id')->on('hrms_pay_runs')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrms_gl_batches');
        Schema::dropIfExists('hrms_pay_run_items');
        Schema::dropIfExists('hrms_pay_runs');
        Schema::dropIfExists('hrms_claims');
        Schema::dropIfExists('hrms_loan_installments');
        Schema::dropIfExists('hrms_loans');
        Schema::dropIfExists('hrms_salary_revisions');
        Schema::dropIfExists('hrms_salary_structure_items');
        Schema::dropIfExists('hrms_salary_structures');
        Schema::dropIfExists('hrms_statutory_configs');
        Schema::dropIfExists('hrms_pay_groups');
        Schema::dropIfExists('hrms_bank_templates');
        Schema::dropIfExists('hrms_salary_components');
    }
};
