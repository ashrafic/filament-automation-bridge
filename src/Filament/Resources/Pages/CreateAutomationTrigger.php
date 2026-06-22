<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages;

use Ashrafic\FilamentAutomationBridge\Filament\Resources\AutomationTriggerResource;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTemplate;
use Ashrafic\FilamentAutomationBridge\Services\TemplateManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationTrigger extends CreateRecord
{
    protected static string $resource = AutomationTriggerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $templateId = request()->query('template_id');

        if ($templateId && $template = AutomationTemplate::find($templateId)) {
            return array_merge($data, app(TemplateManager::class)->applyTemplate($template));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_from_template')
                ->label('Create from Template')
                ->icon('heroicon-o-bookmark')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(
                            AutomationTemplate::orderBy('name')->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $this->redirect(
                        AutomationTriggerResource::getUrl('create', ['template_id' => $data['template_id']])
                    );
                }),
        ];
    }
}
