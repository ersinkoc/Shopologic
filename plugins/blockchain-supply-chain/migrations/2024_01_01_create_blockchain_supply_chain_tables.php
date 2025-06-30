<?php


declare(strict_types=1);

namespace Shopologic\Plugins\BlockchainSupplyChain;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateBlockchainSupplyChainTables extends Migration
{
    public function up(): void
    {
        // Supply chain events on blockchain
        Schema::create('supply_chain_events', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('event_type'); // manufacturing, shipping, receiving, quality_check
            $table->json('event_data');
            $table->string('transaction_hash');
            $table->string('block_hash')->nullable();
            $table->integer('block_number')->nullable();
            $table->boolean('blockchain_confirmed')->default(false);
            $table->timestamp('blockchain_timestamp')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'event_type']);
            $table->index('transaction_hash');
            $table->index(['blockchain_confirmed', 'created_at']);
        });

        // Product authentication certificates
        Schema::create('authenticity_certificates', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('certificate_hash');
            $table->string('certificate_type'); // manufacturing, authenticity, quality, shipping
            $table->json('certificate_data');
            $table->string('issuer');
            $table->string('blockchain_transaction')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
            
            $table->index(['product_id', 'certificate_type']);
            $table->unique('certificate_hash');
            $table->index('is_valid');
        });

        // Blockchain verification results
        Schema::create('blockchain_verifications', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('verification_type'); // authenticity, supply_chain, certificate
            $table->json('verification_data');
            $table->decimal('authenticity_score', 3, 2);
            $table->boolean('is_authentic')->default(true);
            $table->json('verification_details');
            $table->string('verifier_address')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
            
            $table->index(['product_id', 'verification_type']);
            $table->index(['is_authentic', 'verified_at']);
        });

        // Anti-counterfeiting reports
        Schema::create('counterfeit_reports', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('reporter_id')->nullable()->constrained('users');
            $table->string('report_type'); // fake_product, suspicious_seller, quality_issue
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->string('suspected_source')->nullable();
            $table->string('status'); // pending, investigating, confirmed, dismissed
            $table->json('investigation_notes')->nullable();
            $table->string('blockchain_record')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'status']);
            $table->index('reported_at');
        });

        // Supply chain participants
        Schema::create('supply_chain_participants', function($table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // manufacturer, supplier, distributor, retailer
            $table->string('blockchain_address');
            $table->json('credentials');
            $table->json('certifications')->nullable();
            $table->string('location');
            $table->boolean('is_verified')->default(false);
            $table->decimal('trust_score', 3, 2)->default(0.5);
            $table->timestamps();
            
            $table->unique('blockchain_address');
            $table->index(['type', 'is_verified']);
        });

        // Product batches on blockchain
        Schema::create('product_batches', function($table) {
            $table->id();
            $table->string('batch_number');
            $table->foreignId('manufacturer_id')->constrained('supply_chain_participants');
            $table->json('product_ids');
            $table->json('materials_used');
            $table->json('quality_metrics');
            $table->string('blockchain_hash');
            $table->timestamp('manufactured_at');
            $table->boolean('is_recalled')->default(false);
            $table->timestamps();
            
            $table->unique('batch_number');
            $table->index('blockchain_hash');
            $table->index(['manufacturer_id', 'manufactured_at']);
        });

        // Blockchain transactions
        Schema::create('blockchain_transactions', function($table) {
            $table->id();
            $table->string('transaction_hash');
            $table->string('transaction_type'); // record_event, verify_product, issue_certificate
            $table->json('transaction_data');
            $table->string('from_address');
            $table->string('to_address')->nullable();
            $table->decimal('gas_used', 15, 0)->nullable();
            $table->decimal('gas_price', 20, 0)->nullable();
            $table->string('status'); // pending, confirmed, failed
            $table->integer('confirmations')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            $table->unique('transaction_hash');
            $table->index(['status', 'submitted_at']);
            $table->index('transaction_type');
        });

        // Supply chain audit logs
        Schema::create('supply_chain_audits', function($table) {
            $table->id();
            $table->string('audit_type'); // product, batch, participant, system
            $table->string('audited_entity_type');
            $table->unsignedBigInteger('audited_entity_id');
            $table->json('audit_criteria');
            $table->json('audit_results');
            $table->boolean('passed_audit')->default(true);
            $table->json('issues_found')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('auditor');
            $table->timestamp('audited_at');
            $table->timestamps();
            
            $table->index(['audited_entity_type', 'audited_entity_id']);
            $table->index(['passed_audit', 'audited_at']);
        });

        // Smart contracts
        Schema::create('smart_contracts', function($table) {
            $table->id();
            $table->string('contract_name');
            $table->string('contract_address');
            $table->string('contract_type'); // supply_chain, authenticity, batch_tracking
            $table->json('contract_abi');
            $table->string('deployment_transaction');
            $table->string('network'); // ethereum, polygon, private
            $table->boolean('is_active')->default(true);
            $table->timestamp('deployed_at');
            $table->timestamps();
            
            $table->unique(['contract_address', 'network']);
            $table->index(['contract_type', 'is_active']);
        });

        // Transparency scores
        Schema::create('transparency_scores', function($table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->decimal('overall_score', 3, 2);
            $table->decimal('supply_chain_completeness', 3, 2);
            $table->decimal('verification_level', 3, 2);
            $table->decimal('participant_trustworthiness', 3, 2);
            $table->decimal('blockchain_integrity', 3, 2);
            $table->json('score_breakdown');
            $table->timestamp('calculated_at');
            $table->timestamps();
            
            $table->index(['product_id', 'calculated_at']);
            $table->index('overall_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transparency_scores');
        Schema::dropIfExists('smart_contracts');
        Schema::dropIfExists('supply_chain_audits');
        Schema::dropIfExists('blockchain_transactions');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('supply_chain_participants');
        Schema::dropIfExists('counterfeit_reports');
        Schema::dropIfExists('blockchain_verifications');
        Schema::dropIfExists('authenticity_certificates');
        Schema::dropIfExists('supply_chain_events');
    }
}