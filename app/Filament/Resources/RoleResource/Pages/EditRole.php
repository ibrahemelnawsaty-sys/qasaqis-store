<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Prefill the checkbox list with only the permissions the actor is allowed to
     * see/change; permissions outside the actor's authority stay untouched (see
     * handleRecordUpdate), so they are never shown as unchecked and stripped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $assignable = array_keys(RoleResource::assignablePermissionNames());

        $data['permissions'] = array_values(array_intersect(
            $this->record->permissions->pluck('name')->all(),
            $assignable,
        ));

        return $data;
    }

    /**
     * Persist the role, then reconcile permissions: apply the actor's allowed
     * picks but PRESERVE any permission the actor cannot grant, so a lower-
     * privileged actor can neither add a permission above their authority nor
     * silently strip one they cannot see. Mirrors EditUser::handleRecordUpdate.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $assignable = array_keys(RoleResource::assignablePermissionNames());

        $picked = array_intersect($data['permissions'] ?? [], $assignable);
        $preserved = array_diff($record->permissions->pluck('name')->all(), $assignable);

        $finalPermissions = array_values(array_unique(array_merge($picked, $preserved)));

        unset($data['permissions']);

        /** @var Role $record */
        $record->update($data);
        $record->syncPermissions($finalPermissions);

        return $record;
    }
}
