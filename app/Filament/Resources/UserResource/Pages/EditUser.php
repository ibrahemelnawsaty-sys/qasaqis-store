<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Prefill the roles select with only the roles the actor is allowed to see/
     * change; roles outside the actor's authority stay untouched (see update).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $assignable = array_keys(UserResource::assignableRoleNames());

        $data['roles'] = array_values(array_intersect(
            $this->record->roles->pluck('name')->all(),
            $assignable,
        ));

        return $data;
    }

    /**
     * Persist the user, then reconcile roles: apply the actor's allowed picks but
     * PRESERVE any role the actor cannot manage (e.g. a peer's «admin» role), so
     * a lower-privileged actor can neither grant nor strip roles above them.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $assignable = array_keys(UserResource::assignableRoleNames());

        $picked = array_intersect($data['roles'] ?? [], $assignable);
        $preserved = array_diff($record->roles->pluck('name')->all(), $assignable);

        $finalRoles = array_values(array_unique(array_merge($picked, $preserved)));

        unset($data['roles']);

        /** @var User $record */
        $record->update($data);
        $record->syncRoles($finalRoles);

        return $record;
    }
}
