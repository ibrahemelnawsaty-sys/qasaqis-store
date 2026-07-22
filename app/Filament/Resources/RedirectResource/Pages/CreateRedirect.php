<?php

declare(strict_types=1);

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Resources\RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    protected static string $resource = RedirectResource::class;

    /**
     * التحويلات المُضافة يدويًا من اللوحة مصدرها «manual» (تمييزًا عن التلقائية).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = 'manual';

        return $data;
    }
}
