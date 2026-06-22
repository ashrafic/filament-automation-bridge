<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages;

use Ashrafic\FilamentAutomationBridge\Filament\Resources\AutomationTriggerResource;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTemplate;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ListAutomationTemplates extends ListRecords
{
    protected static string $resource = AutomationTriggerResource::class;

    public static function getNavigationLabel(): string
    {
        return 'Templates';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AutomationTemplate::query())
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
                Tables\Columns\IconColumn::make('is_builtin')
                    ->label('Built-in')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('apply')
                    ->label('Use Template')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (AutomationTemplate $record) => AutomationTriggerResource::getUrl('create', ['template_id' => $record->id])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AutomationTemplate $record) => ! $record->is_builtin),
            ])
            ->emptyStateHeading('No templates yet')
            ->emptyStateDescription('Save a trigger configuration as a template from the Edit or View page.');
    }
}
