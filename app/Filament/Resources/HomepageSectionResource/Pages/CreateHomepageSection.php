<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Filament\Resources\HomepageSectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomepageSection extends CreateRecord
{
    protected static string $resource = HomepageSectionResource::class;
}
