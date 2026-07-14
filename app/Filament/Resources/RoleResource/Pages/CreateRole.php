<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Create the role, then grant ONLY the permissions the current actor is
     * allowed to grant. Intersecting with assignablePermissionNames() re-checks
     * server-side, so a tampered request cannot inject a permission the actor
     * does not hold (privilege escalation). Mirrors CreateUser::handleRecordCreation.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $submitted = array_values(array_intersect(
            $data['permissions'] ?? [],
            array_keys(RoleResource::assignablePermissionNames()),
        ));

        unset($data['permissions']);

        /** @var Role $role */
        $role = static::getModel()::create($data);
        $role->syncPermissions($submitted);

        return $role;
    }
}
