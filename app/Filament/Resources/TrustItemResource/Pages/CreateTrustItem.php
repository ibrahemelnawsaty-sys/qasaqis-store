<?php

declare(strict_types=1);

namespace App\Filament\Resources\TrustItemResource\Pages;

use App\Filament\Resources\TrustItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrustItem extends CreateRecord
{
    protected static string $resource = TrustItemResource::class;
}
