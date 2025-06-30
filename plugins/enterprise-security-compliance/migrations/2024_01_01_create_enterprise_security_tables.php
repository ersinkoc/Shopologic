<?php


declare(strict_types=1);

namespace Shopologic\Plugins\EnterpriseSecurityCompliance;
declare(strict_types=1);
use Shopologic\Database\Migration;
use Shopologic\Database\Schema;

class CreateEnterpriseSecurityTables extends Migration
{
    public function up(): void
    {
        // Audit logs
        Schema::create('audit_logs', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('event_type');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['store_id', 'event_type', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('is_sensitive');
        });

        // Security incidents
        Schema::create('security_incidents', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('incident_type'); // brute_force, data_breach, unauthorized_access
            $table->string('severity'); // low, medium, high, critical
            $table->string('status'); // detected, investigating, resolved, dismissed
            $table->text('description');
            $table->json('incident_data');
            $table->string('source_ip')->nullable();
            $table->foreignId('affected_user_id')->nullable()->constrained('users');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->json('resolution_notes')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'severity', 'status']);
            $table->index(['incident_type', 'detected_at']);
            $table->index('source_ip');
        });

        // Vulnerability scans
        Schema::create('vulnerability_scans', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('scan_type'); // automated, manual, scheduled
            $table->string('scan_scope'); // full_system, specific_component
            $table->string('status'); // running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('vulnerabilities_found')->default(0);
            $table->integer('critical_vulnerabilities')->default(0);
            $table->integer('high_vulnerabilities')->default(0);
            $table->integer('medium_vulnerabilities')->default(0);
            $table->integer('low_vulnerabilities')->default(0);
            $table->json('scan_results')->nullable();
            $table->json('scan_config')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'status']);
            $table->index('started_at');
        });

        // Vulnerabilities
        Schema::create('vulnerabilities', function($table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('vulnerability_scans');
            $table->string('vulnerability_id'); // CVE ID or internal ID
            $table->string('title');
            $table->text('description');
            $table->string('severity'); // critical, high, medium, low
            $table->string('category'); // sql_injection, xss, authentication, etc.
            $table->string('component'); // affected system component
            $table->string('status'); // new, acknowledged, fixing, fixed, dismissed
            $table->json('technical_details');
            $table->json('remediation_steps')->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('patched_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index(['severity', 'status']);
            $table->index('vulnerability_id');
            $table->index('discovered_at');
        });

        // Threat detection rules
        Schema::create('threat_detection_rules', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('rule_name');
            $table->string('rule_type'); // pattern_matching, anomaly_detection, behavioral
            $table->text('description');
            $table->json('rule_config');
            $table->string('severity'); // low, medium, high, critical
            $table->boolean('is_active')->default(true);
            $table->integer('trigger_count')->default(0);
            $table->timestamp('last_triggered')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
            $table->index('rule_type');
        });

        // Security threats
        Schema::create('security_threats', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('rule_id')->nullable()->constrained('threat_detection_rules');
            $table->string('threat_type');
            $table->string('severity');
            $table->string('status'); // active, investigating, mitigated, false_positive
            $table->string('source_ip')->nullable();
            $table->json('threat_data');
            $table->decimal('risk_score', 5, 2);
            $table->timestamp('detected_at');
            $table->timestamp('mitigated_at')->nullable();
            $table->json('mitigation_actions')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'threat_type', 'status']);
            $table->index(['severity', 'detected_at']);
            $table->index('source_ip');
        });

        // IP address blocks
        Schema::create('blocked_ips', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('ip_address');
            $table->string('block_reason');
            $table->string('block_type'); // temporary, permanent, automated
            $table->timestamp('blocked_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('blocked_by')->nullable()->constrained('users');
            $table->json('additional_data')->nullable();
            $table->timestamps();
            
            $table->index(['ip_address', 'is_active']);
            $table->index(['store_id', 'is_active']);
            $table->index('expires_at');
        });

        // Compliance frameworks
        Schema::create('compliance_frameworks', function($table) {
            $table->id();
            $table->string('framework_name'); // GDPR, PCI-DSS, CCPA, SOX
            $table->text('description');
            $table->json('requirements');
            $table->string('version');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('framework_name');
        });

        // Compliance status
        Schema::create('compliance_status', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('framework_id')->constrained('compliance_frameworks');
            $table->string('compliance_level'); // compliant, non_compliant, partially_compliant
            $table->decimal('compliance_score', 5, 2);
            $table->json('requirement_status');
            $table->date('assessment_date');
            $table->timestamp('next_assessment')->nullable();
            $table->json('findings')->nullable();
            $table->json('remediation_plan')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'framework_id', 'assessment_date']);
            $table->index('assessment_date');
        });

        // GDPR data requests
        Schema::create('gdpr_data_requests', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('request_type'); // access, portability, deletion, rectification
            $table->string('status'); // pending, processing, completed, rejected
            $table->json('request_details');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('response_data')->nullable();
            $table->string('fulfillment_method')->nullable(); // email, download, api
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'request_type']);
            $table->index(['status', 'requested_at']);
        });

        // Consent management
        Schema::create('consent_records', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('consent_type'); // marketing, analytics, cookies, data_processing
            $table->boolean('consent_given');
            $table->string('consent_version');
            $table->timestamp('consent_timestamp');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('consent_details')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            
            $table->index(['user_id', 'consent_type', 'is_current']);
            $table->index('consent_timestamp');
        });

        // Security configurations
        Schema::create('security_configurations', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->string('config_category'); // authentication, encryption, access_control
            $table->string('config_key');
            $table->text('config_value');
            $table->text('description')->nullable();
            $table->string('security_level'); // basic, standard, strict
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_modified');
            $table->foreignId('modified_by')->constrained('users');
            $table->timestamps();
            
            $table->unique(['store_id', 'config_category', 'config_key']);
            $table->index('security_level');
        });

        // Data classification
        Schema::create('data_classifications', function($table) {
            $table->id();
            $table->string('data_type');
            $table->string('classification_level'); // public, internal, confidential, restricted
            $table->json('handling_requirements');
            $table->integer('retention_period_days');
            $table->boolean('requires_encryption')->default(false);
            $table->boolean('requires_audit')->default(false);
            $table->json('compliance_requirements')->nullable();
            $table->timestamps();
            
            $table->unique('data_type');
            $table->index('classification_level');
        });

        // Security metrics
        Schema::create('security_metrics', function($table) {
            $table->id();
            $table->foreignId('store_id')->constrained();
            $table->date('metric_date');
            $table->integer('login_attempts')->default(0);
            $table->integer('failed_logins')->default(0);
            $table->integer('blocked_ips')->default(0);
            $table->integer('security_incidents')->default(0);
            $table->integer('vulnerabilities_found')->default(0);
            $table->integer('vulnerabilities_fixed')->default(0);
            $table->decimal('security_score', 5, 2)->default(0);
            $table->json('additional_metrics')->nullable();
            $table->timestamps();
            
            $table->unique(['store_id', 'metric_date']);
            $table->index('metric_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_metrics');
        Schema::dropIfExists('data_classifications');
        Schema::dropIfExists('security_configurations');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('gdpr_data_requests');
        Schema::dropIfExists('compliance_status');
        Schema::dropIfExists('compliance_frameworks');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('security_threats');
        Schema::dropIfExists('threat_detection_rules');
        Schema::dropIfExists('vulnerabilities');
        Schema::dropIfExists('vulnerability_scans');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('audit_logs');
    }
}