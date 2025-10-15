<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:cleanup-test-data 
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all test/organization data, keeping only superadmins and global competencies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ========================================
        // CRITICAL SAFETY CHECK - PREVENT PRODUCTION USE
        // ========================================
        $saasEnv = env('SAAS_ENV');
        
        if ($saasEnv !== 'test') {
            $this->error('╔═══════════════════════════════════════════════════════════╗');
            $this->error('║  🚨 BLOCKED - PRODUCTION SAFETY CHECK FAILED  🚨          ║');
            $this->error('╚═══════════════════════════════════════════════════════════╝');
            $this->newLine();
            $this->error('This command can ONLY run when SAAS_ENV=test');
            $this->error('Current SAAS_ENV: ' . ($saasEnv ?: 'NOT SET'));
            $this->newLine();
            $this->warn('This is a safety feature to prevent accidental data loss on production.');
            $this->warn('If you need to run this command, set SAAS_ENV=test in your .env file.');
            $this->newLine();
            $this->info('Expected .env configuration:');
            $this->line('  Local/Test: SAAS_ENV=test');
            $this->line('  Staging:    SAAS_ENV=staging');
            $this->line('  Production: SAAS_ENV=production');
            $this->newLine();
            
            return 1; // Exit with error code
        }
        
        $this->warn('╔═══════════════════════════════════════════════════════════╗');
        $this->warn('║  ⚠️  DATABASE CLEANUP - DANGER ZONE  ⚠️                    ║');
        $this->warn('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        // Show environment confirmation
        $this->info('Environment Check: ✓ SAAS_ENV=test (Safe to proceed)');
        $this->newLine();
        
        $this->info('This command will DELETE:');
        $this->line('  • All organizations and their data');
        $this->line('  • All assessments and results');
        $this->line('  • All non-superadmin users');
        $this->line('  • All organization-specific competencies');
        $this->line('  • All organization-specific CEO ranks');
        $this->line('  • All user relations and competency assignments');
        $this->line('  • All competency submissions');
        $this->newLine();
        
        $this->info('This command will KEEP:');
        $this->line('  • Superadmin users');
        $this->line('  • Global competencies (organization_id = NULL)');
        $this->line('  • Default CEO ranks (organization_id = NULL)');
        $this->newLine();

        // Show current counts
        $this->showCurrentCounts();
        
        if (!$this->option('force')) {
            if (!$this->confirm('Are you ABSOLUTELY SURE you want to proceed?', false)) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
            
            $this->newLine();
            if (!$this->confirm('Type YES in capital letters to confirm', false)) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $this->newLine();
        $this->warn('Starting cleanup in 3 seconds... Press Ctrl+C to cancel!');
        sleep(3);
        
        DB::beginTransaction();
        
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            
            $this->info('🗑️  Cleaning up database...');
            $this->newLine();
            
            // Section 1: Assessment data
            $this->line('→ Deleting assessment data...');
            DB::table('assessment_bonuses')->delete();
            DB::table('competency_submit')->delete();
            DB::table('user_competency_submit')->delete();
            DB::table('user_ceo_rank')->delete();
            DB::table('assessment')->delete();
            $this->info('  ✓ Assessment data deleted');
            
            // Section 2: User-organization data
            $this->line('→ Deleting user-organization data...');
            DB::table('user_bonus_malus')->delete();
            DB::table('user_wages')->delete();
            DB::table('user_competency_sources')->delete();
            DB::table('user_competency')->whereNotNull('organization_id')->delete();
            DB::table('user_relation')->delete();
            $this->info('  ✓ User-organization data deleted');
            
            // Section 3: Organization-specific competencies
            $this->line('→ Deleting organization competencies...');
            
            // Delete competency questions for org-specific competencies
            DB::statement('
                DELETE cq FROM competency_question cq
                INNER JOIN competency c ON cq.competency_id = c.id
                WHERE c.organization_id IS NOT NULL
            ');
            
            DB::table('competency')->whereNotNull('organization_id')->delete();
            DB::table('competency_groups')->delete();
            $this->info('  ✓ Organization competencies deleted');
            
            // Section 4: Organization-specific CEO ranks
            $this->line('→ Deleting organization CEO ranks...');
            DB::table('ceo_rank')->whereNotNull('organization_id')->delete();
            $this->info('  ✓ Organization CEO ranks deleted');
            
            // Section 5: Organization structure
            $this->line('→ Deleting organization structure...');
            DB::table('organization_department_managers')->delete();
            DB::table('organization_departments')->delete();
            DB::table('bonus_malus_config')->delete();
            DB::table('organization_config')->delete();
            DB::table('payments')->delete();
            DB::table('organization_profiles')->delete();
            $this->info('  ✓ Organization structure deleted');
            
            // Section 6: Organizations
            $this->line('→ Deleting organizations...');
            DB::table('organization_user')->delete();
            DB::table('organization')->delete();
            $this->info('  ✓ Organizations deleted');
            
            // Section 7: Non-superadmin users
            $this->line('→ Deleting non-superadmin users...');
            $userIds = DB::table('user')
                ->where('type', '!=', 'superadmin')
                ->pluck('id');
            
            if ($userIds->isNotEmpty()) {
                DB::table('user_login')->whereIn('user_id', $userIds)->delete();
                DB::table('user')->whereIn('id', $userIds)->delete();
            }
            $this->info('  ✓ Non-superadmin users deleted');
            
            // Section 8: System tables
            $this->line('→ Cleaning system tables...');
            DB::table('failed_jobs')->delete();
            DB::table('jobs')->delete();
            DB::table('password_resets')->delete();
            $this->info('  ✓ System tables cleaned');
            
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            
            DB::commit();
            
            $this->newLine();
            $this->info('✅ Cleanup completed successfully!');
            $this->newLine();
            
            // Show final counts
            $this->showFinalCounts();
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->error('❌ Cleanup failed: ' . $e->getMessage());
            $this->error('Transaction rolled back. Database unchanged.');
            $this->newLine();
            $this->error('Full error: ' . $e->getTraceAsString());
            
            return 1;
        }
    }
    
    /**
     * Show current database counts
     */
    protected function showCurrentCounts()
    {
        $this->info('📊 Current database status:');
        
        $organizations = DB::table('organization')->count();
        $users = DB::table('user')->count();
        $superadmins = DB::table('user')->where('type', 'superadmin')->count();
        $assessments = DB::table('assessment')->count();
        $globalCompetencies = DB::table('competency')->whereNull('organization_id')->count();
        $orgCompetencies = DB::table('competency')->whereNotNull('organization_id')->count();
        $relations = DB::table('user_relation')->count();
        $submissions = DB::table('competency_submit')->count();
        
        $this->table(
            ['Item', 'Count'],
            [
                ['Organizations', $organizations],
                ['Total Users', $users],
                ['Superadmins (will keep)', $superadmins],
                ['Regular Users (will delete)', $users - $superadmins],
                ['Assessments', $assessments],
                ['Global Competencies (will keep)', $globalCompetencies],
                ['Org Competencies (will delete)', $orgCompetencies],
                ['User Relations (will delete)', $relations],
                ['Competency Submissions (will delete)', $submissions],
            ]
        );
        
        $this->newLine();
    }
    
    /**
     * Show final database counts after cleanup
     */
    protected function showFinalCounts()
    {
        $this->info('📊 Final database status:');
        
        $users = DB::table('user')->count();
        $globalCompetencies = DB::table('competency')->whereNull('organization_id')->count();
        $globalCeoRanks = DB::table('ceo_rank')->whereNull('organization_id')->count();
        $relations = DB::table('user_relation')->count();
        $submissions = DB::table('competency_submit')->count();
        
        $this->table(
            ['Item', 'Count'],
            [
                ['Organizations', 0],
                ['Users (superadmins only)', $users],
                ['Assessments', 0],
                ['Global Competencies', $globalCompetencies],
                ['Global CEO Ranks', $globalCeoRanks],
                ['User Relations', $relations],
                ['Competency Submissions', $submissions],
            ]
        );
        
        $this->newLine();
        $this->info('💡 You can now create new organizations and test data.');
        $this->info('💡 Superadmin users are still active and can login.');
    }
}