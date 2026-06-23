<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Pages;

use Ashrafic\FilamentAutomationBridge\Filament\Resources\AutomationTriggerResource;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTemplate;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ListAutomationTemplates extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?int $navigationSort = 82;

    protected static ?string $slug = 'automation-bridge/templates';

    protected string $view = 'filament-automation-bridge::pages.template-list';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-automation-bridge.ui.navigation_group', 'Integrations');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-bookmark';
    }

    public static function getNavigationLabel(): string
    {
        return 'Templates';
    }

    public static function getModelLabel(): string
    {
        return 'Template';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Templates';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('automation_bridge.view_triggers') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AutomationTemplate::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('model_class')
                    ->label('Model')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->getLabel()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('apply')
                    ->label('Use Template')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (AutomationTemplate $record) => AutomationTriggerResource::getUrl('create', ['template_id' => $record->id])),
                Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No templates yet')
            ->emptyStateDescription('Save a trigger configuration as a template from the Edit or View page.');
    }
}
