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
        return __('filament-automation-bridge::navigation.group');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-bookmark';
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-automation-bridge::navigation.templates');
    }

    public static function getModelLabel(): string
    {
        return __('filament-automation-bridge::labels.template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-automation-bridge::labels.templates');
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
                    ->label(__('filament-automation-bridge::table.model'))
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->label(__('filament-automation-bridge::table.event'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->getLabel()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-automation-bridge::table.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Actions\Action::make('apply')
                    ->label(__('filament-automation-bridge::actions.use_template'))
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (AutomationTemplate $record) => AutomationTriggerResource::getUrl('create', ['template_id' => $record->id])),
                Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading(__('filament-automation-bridge::table.empty_templates_heading'))
            ->emptyStateDescription(__('filament-automation-bridge::table.empty_templates_description'));
    }
}
