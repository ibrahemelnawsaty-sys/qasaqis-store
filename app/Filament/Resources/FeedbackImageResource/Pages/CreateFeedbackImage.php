<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFeedbackImage extends CreateRecord
{
    protected static string $resource = FeedbackImageResource::class;
}
