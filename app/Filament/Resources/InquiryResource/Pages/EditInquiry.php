<?php

declare(strict_types=1);

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditInquiry extends EditRecord
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Stamp the responder and reply time whenever a reply is present, so the
     * audit fields (assigned_to, replied_at) reflect who actually answered.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['admin_reply'] ?? null)) {
            $data['assigned_to'] = $this->record->assigned_to ?? Auth::id();
            $data['replied_at'] = $this->record->replied_at ?? now();
        }

        return $data;
    }
}
