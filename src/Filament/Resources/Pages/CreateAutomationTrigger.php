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

    public function mount(): void
    {
        parent::mount();

        $templateId = request()->query('template_id');

        if ($templateId && $template = AutomationTemplate::find($templateId)) {
            $this->form->fill(app(TemplateManager::class)->applyTemplate($template));
        }
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
                    return redirect(
                        AutomationTriggerResource::getUrl('create', ['template_id' => $data['template_id']])
                    );
                }),
        ];
    }
}
