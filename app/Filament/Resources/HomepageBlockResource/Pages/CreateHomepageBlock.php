<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageBlockResource\Pages;

use App\Filament\Resources\HomepageBlockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomepageBlock extends CreateRecord
{
    protected static string $resource = HomepageBlockResource::class;
}
