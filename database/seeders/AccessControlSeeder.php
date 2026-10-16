<?php

namespace Kevinbharris1991\LaravelAccessControlSystem\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Group;
use Kevinbharris1991\LaravelAccessControlSystem\Models\Permission;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->defaults() as $slug => [$name, $focus, $resources]) {
                $group = Group::firstOrCreate(['slug' => $slug], compact('name', 'focus'));
                $permissions = [];

                foreach ($resources as $resource => $actions) {
                    foreach (explode(',', $actions) as $action) {
                        $permission = Permission::firstOrCreate(
                            ['slug' => "$resource.$action"],
                            ['name' => Str::headline("$resource $action")],
                        );
                        $permissions[] = $permission->id;
                    }
                }

                $group->permissions()->syncWithoutDetaching($permissions);
            }
        });
    }

    private function defaults(): array
    {
        return [
            'executive-director' => ['Executive Director', 'Owner & Mission Leader', [
                'system-configuration' => 'read',
                'executive-users' => 'create,read,update,delete',
                'audit-logs' => 'read',
                'camper-summaries' => 'read',
                'revenue-reports' => 'create,read,update',
                'budgets' => 'create,read,update',
                'grant-applications' => 'create,read,update',
                'financial-approvals' => 'approve',
                'public-content' => 'create,read,update,delete',
            ]],
            'administrative-directors' => ['Administrative Directors', 'Operations & Governance', [
                'staff-users' => 'create,read,update,delete',
                'groups' => 'create,read,update,delete',
                'permissions' => 'create,read,update,delete',
                'group-memberships' => 'read,update',
                'staff-credentials' => 'reset',
                'password-rules' => 'read,update',
                'scheduling' => 'create,read,update',
                'governance' => 'create,read,update',
                'camper-intake' => 'create,read,update',
                'compliance-documents' => 'audit',
                'administrative-reports' => 'read,export',
                'form-templates' => 'create,read,update,delete',
            ]],
            'medical-nursing-staff' => ['Medical & Nursing Staff', 'Health & Safety', [
                'medications' => 'create,read,update,delete',
                'health-profiles' => 'create,read,update',
                'clinical-incidents' => 'create,read,update',
            ]],
            'behavioral-support-services' => ['Behavioral Support Services', 'Autism, ADHD, IDD Support', [
                'support-plans' => 'create,read,update,delete',
                'behavioral-tracking' => 'create,read,update',
                'behavior-relevant-health' => 'read',
                'staff-consultation-notes' => 'create,read,update,publish',
            ]],
            'accessibility-adaptive-services' => ['Accessibility & Adaptive Services', 'Mobility & ADA Compliance', [
                'camper-accommodations' => 'read,update',
                'adaptive-equipment' => 'create,read,update,delete',
                'facility-audits' => 'create,read,update',
                'accessibility-work-orders' => 'create,read',
            ]],
            'program-directors' => ['Program Directors', 'Activities & Recreation', [
                'master-schedule' => 'create,read,update,delete',
                'activity-adaptations' => 'create,read,update',
                'camper-participation' => 'read,update',
                'safety-alerts' => 'read',
            ]],
            'counselors-dsps' => ["Counselors & DSP's", 'Camper Support', [
                'assigned-camper-profiles' => 'read',
                'daily-living-logs' => 'create,read',
                'incident-flags' => 'create',
            ]],
            'facilities-cabin-maintenance' => ['Facilities & Cabin Maintenance', 'Safety & Accessibility', [
                'work-orders' => 'read,update,assign',
                'safety-inspections' => 'create,read,update',
                'emergency-alerts' => 'read,update',
            ]],
            'housekeeping' => ['Housekeeping', 'Clean & Sensory-Friendly Spaces', [
                'sanitation-roster' => 'read,update',
                'sensory-room-setup' => 'read,update',
                'supply-requests' => 'create,read',
            ]],
            'catering-dietary-support' => ['Catering & Dietary Support', 'Allergy & Special Diets', [
                'dietary-dashboard' => 'read',
                'menus' => 'create,read,update',
                'special-meal-tracking' => 'read,update',
            ]],
            'finance-accounting' => ['Finance & Accounting', 'Budget & Compliance', [
                'billing' => 'create,read,update,delete',
                'payroll' => 'create,read,update,approve,process',
                'grant-funds' => 'create,read,update',
                'financial-compliance-reports' => 'create,read,export',
            ]],
            'guest' => ['Guest', 'Reservations, Needs & Medical Disclosure', [
                'own-reservations' => 'create,read,update,pay,cancel',
                'own-accommodation-needs' => 'create,read,update',
                'own-health-disclosures' => 'create,read,update',
                'own-dietary-needs' => 'create,read,update',
            ]],
        ];
    }
}
