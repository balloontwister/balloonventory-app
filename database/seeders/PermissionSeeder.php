<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Inventory
            'inventory.check_in',
            'inventory.check_out',
            'inventory.manual_adjust',
            'inventory.override_count',
            'inventory.view_counts',
            'inventory.view_audit_log',

            // Catalog — SKUs
            'sku.create_private',
            'sku.edit_private',
            'sku.delete_private',
            'sku.edit_override',
            'sku.report_error',

            // Lists and Favorites
            'list.view',
            'list.create',
            'list.edit',
            'list.delete',
            'favorites.edit',

            // Jobs
            'job.view',
            'job.create',
            'job.edit',
            'job.delete',
            'job.set_status',

            // Local Prices
            'local_price.view',
            'local_price.edit',

            // Membership management
            'membership.invite_owner',
            'membership.invite_manager',
            'membership.invite_staff',
            'membership.invite_guest',
            'membership.change_role_any',
            'membership.change_role_staff_guest',
            'membership.remove_owner',
            'membership.remove_manager',
            'membership.remove_staff_guest',

            // Business settings
            'business.edit_settings',
            'business.manage_logo',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->seedOwner();
        $this->seedManager();
        $this->seedStaff();
        $this->seedGuest();
    }

    private function seedOwner(): void
    {
        $role = Role::findOrCreate('owner', 'web');
        $role->syncPermissions([
            'inventory.check_in',
            'inventory.check_out',
            'inventory.manual_adjust',
            'inventory.override_count',
            'inventory.view_counts',
            'inventory.view_audit_log',
            'sku.create_private',
            'sku.edit_private',
            'sku.delete_private',
            'sku.edit_override',
            'sku.report_error',
            'list.view',
            'list.create',
            'list.edit',
            'list.delete',
            'favorites.edit',
            'job.view',
            'job.create',
            'job.edit',
            'job.delete',
            'job.set_status',
            'local_price.view',
            'local_price.edit',
            'membership.invite_owner',
            'membership.invite_manager',
            'membership.invite_staff',
            'membership.invite_guest',
            'membership.change_role_any',
            'membership.change_role_staff_guest',
            'membership.remove_owner',
            'membership.remove_manager',
            'membership.remove_staff_guest',
            'business.edit_settings',
            'business.manage_logo',
        ]);
    }

    private function seedManager(): void
    {
        $role = Role::findOrCreate('manager', 'web');
        $role->syncPermissions([
            'inventory.check_in',
            'inventory.check_out',
            'inventory.manual_adjust',
            'inventory.override_count',
            'inventory.view_counts',
            'inventory.view_audit_log',
            'sku.create_private',
            'sku.edit_private',
            'sku.delete_private',
            'sku.edit_override',
            'sku.report_error',
            'list.view',
            'list.create',
            'list.edit',
            'list.delete',
            'favorites.edit',
            'job.view',
            'job.create',
            'job.edit',
            'job.delete',
            'job.set_status',
            'local_price.view',
            'local_price.edit',
            'membership.invite_staff',
            'membership.invite_guest',
            'membership.change_role_staff_guest',
            'membership.remove_staff_guest',
            'business.edit_settings',
        ]);
    }

    private function seedStaff(): void
    {
        $role = Role::findOrCreate('staff', 'web');
        $role->syncPermissions([
            'inventory.check_in',
            'inventory.check_out',
            'inventory.manual_adjust',
            'inventory.override_count',
            'inventory.view_counts',
            'inventory.view_audit_log',
            'sku.create_private',
            'sku.edit_private',
            'sku.report_error',
            'list.view',
            'list.create',
            'list.edit',
            'list.delete',
            'favorites.edit',
            'job.view',
            'job.create',
            'job.edit',
            'job.set_status',
            'local_price.view',
            'local_price.edit',
        ]);
    }

    private function seedGuest(): void
    {
        $role = Role::findOrCreate('guest', 'web');
        $role->syncPermissions([
            'inventory.view_counts',
            'sku.report_error',
            'list.view',
            'list.create',
            'list.edit',
        ]);
    }
}
