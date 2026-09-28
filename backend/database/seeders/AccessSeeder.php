<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view', 'items.view', 'items.manage', 'items.delete', 'items.purge',
            'stock.view', 'stock.entry', 'stock.exit', 'stock.exit_confirm', 'stock.adjust',
            'stock.transfer', 'stock.receive', 'lookups.view', 'lookups.manage', 'lookups.delete',
            'users.view', 'users.manage', 'roles.manage', 'requests.create', 'requests.view_own',
            'requests.view_department', 'requests.view_all', 'requests.approve', 'requests.process',
            'requests.cancel_any', 'events.view', 'events.manage', 'events.withdraw', 'deliveries.view',
            'rules.manage', 'reports.view', 'reports.export', 'import.run', 'alerts.stock',
            'audit.view', 'settings.manage',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(function (string $slug): array {
            return [$slug => Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('.', ' — ', ucfirst($slug)), 'module' => explode('.', $slug)[0]],
            )];
        });

        $all = $permissionModels->keys()->all();
        $roles = [
            'admin' => ['Administrador', $all],
            'approver' => ['TRADE / Gestor', [
                'dashboard.view', 'items.view', 'items.manage', 'stock.view', 'stock.entry', 'stock.exit',
                'stock.transfer', 'lookups.view', 'lookups.manage', 'requests.create', 'requests.view_department',
                'requests.view_all', 'requests.approve', 'requests.process', 'events.view', 'events.manage',
                'events.withdraw', 'deliveries.view', 'reports.view', 'reports.export',
            ]],
            'operations' => ['CD / Estoque', [
                'dashboard.view', 'stock.view', 'stock.entry', 'stock.exit_confirm', 'stock.transfer',
                'stock.receive', 'deliveries.view', 'reports.view',
            ]],
            'requester' => ['TRADE', [
                'dashboard.view', 'items.view', 'requests.create', 'requests.view_own', 'lookups.view',
            ]],
            'industry' => ['Indústria', ['dashboard.view', 'items.view', 'requests.view_own', 'reports.view']],
        ];

        foreach ($roles as $slug => [$name, $rolePermissions]) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => 'Perfil do sistema Gestão de Brindes.'],
            );
            $role->permissions()->sync(collect($rolePermissions)->map(fn (string $item) => $permissionModels[$item]->id)->all());
        }
    }
}
