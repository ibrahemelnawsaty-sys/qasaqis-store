<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Create the user, then sync ONLY the roles the current actor is allowed to
     * grant. Intersecting with assignableRoleNames() re-checks server-side, so a
     * tampered request cannot inject a higher role than the actor may assign.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $submittedRoles = array_values(array_intersect(
            $data['roles'] ?? [],
            array_keys(UserResource::assignableRoleNames()),
        ));

        unset($data['roles']);

        /** @var User $user */
        $user = static::getModel()::create($data);
        $user->syncRoles($submittedRoles);

        return $user;
    }
}
