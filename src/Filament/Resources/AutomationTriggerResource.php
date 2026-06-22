<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Resources;

use Ashrafic\FilamentAutomationBridge\Enums\DestinationType;
use Ashrafic\FilamentAutomationBridge\Enums\EventEnum;
use Ashrafic\FilamentAutomationBridge\Enums\PayloadMode;
use Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages\CreateAutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages\EditAutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages\ListAutomationTriggers;
use Ashrafic\FilamentAutomationBridge\Filament\Resources\Pages\ViewAutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Models\AutomationTrigger;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Ashrafic\FilamentAutomationBridge\Services\FieldSchemaAnalyzer;
use Ashrafic\FilamentAutomationBridge\Services\ModelDiscoveryService;
use Ashrafic\FilamentAutomationBridge\Triggers\TriggerManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AutomationTriggerResource extends Resource
{
    protected static ?string $model = AutomationTrigger::class;

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-automation-bridge.ui.navigation_group', 'Integrations');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return config('filament-automation-bridge.ui.navigation_icon') ?? 'heroicon-o-bolt';
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-automation-bridge.ui.navigation_sort', 80);
    }

    public static function getModelLabel(): string
    {
        return 'Automation Trigger';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Automation Triggers';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('automation_bridge.view_triggers') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('When this happens...')
                    ->description('Choose the model and event that triggers this automation')
                    ->icon('heroicon-o-bolt')
                    ->collapsible()
                    ->schema(function (Get $get) {
                        $schema = [
                            Forms\Components\Select::make('model_class')
                                ->label('Model')
                                ->options(fn () => app(ModelDiscoveryService::class)->getAllModels())
                                ->searchable()
                                ->required()
                                ->live()
                                ->placeholder('Choose a model...')
                                ->helperText('The Eloquent model to watch for events'),
                            Forms\Components\Select::make('trigger_type')
                                ->label('Trigger Type')
                                ->options(fn () => app(TriggerManager::class)->options())
                                ->default('model-event')
                                ->required()
                                ->live()
                                ->helperText('How should this automation fire?')
                                ->afterStateUpdated(function (callable $set) {
                                    $set('event', null);
                                    $set('trigger_config', []);
                                }),
                        ];

                        $triggerType = $get('trigger_type') ?? 'model-event';

                        if ($triggerType === 'model-event') {
                            $schema[] = Forms\Components\Select::make('event')
                                ->label('On Event')
                                ->options(EventEnum::class)
                                ->required()
                                ->live()
                                ->helperText('Which model event triggers this?');
                        }

                        $triggerManager = app(TriggerManager::class);

                        if ($triggerManager->get($triggerType)) {
                            $configSchema = $triggerManager->get($triggerType)::configSchema();

                            $skipFields = ['model_class'];

                            if ($triggerType === 'model-event') {
                                $skipFields[] = 'event';
                            }

                            foreach ($configSchema as $field) {
                                $fieldName = $field->getName();

                                if (in_array($fieldName, $skipFields)) {
                                    continue;
                                }

                                if (! str_starts_with($fieldName, 'trigger_config.')) {
                                    $field->statePath('trigger_config.'.$fieldName);
                                }

                                $schema[] = $field;
                            }
                        }

                        $schema[] = Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('A descriptive name to identify this automation');

                        $schema[] = Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Optional notes about what this automation does');

                        return $schema;
                    })
                    ->columns(2)
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make('Only if these conditions match')
                    ->description('Add rules to filter when this automation should fire (skip to always run)')
                    ->icon('heroicon-o-funnel')
                    ->collapsible()
                    ->collapsed(fn ($state) => empty(data_get($state, 'conditions')))
                    ->schema([
                        Forms\Components\Repeater::make('conditions')
                            ->label('')
                            ->addActionLabel('Add Condition')
                            ->schema([
                                Forms\Components\Select::make('field')
                                ->options(function (Get $get) {
                                        $modelClass = $get('../../model_class');

                                        if (! $modelClass) {
                                            return [];
                                        }

                                        $analyzer = app(FieldSchemaAnalyzer::class);
                                        $attributes = $analyzer->getAttributeNames($modelClass);

                                        return collect($attributes)
                                            ->mapWithKeys(fn ($attr) => [
                                                is_array($attr) ? $attr['name'] : $attr => is_array($attr) ? $attr['name'] : $attr,
                                            ])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->placeholder('Field'),
                                Forms\Components\Select::make('operator')
                                    ->options([
                                        'equals' => 'Equals',
                                        'not_equals' => 'Not Equals',
                                        'contains' => 'Contains',
                                        'greater_than' => 'Greater Than',
                                        'less_than' => 'Less Than',
                                        'is_empty' => 'Is Empty',
                                        'is_not_empty' => 'Is Not Empty',
                                        'changed' => 'Changed',
                                        'changed_to' => 'Changed To',
                                    ])
                                    ->required()
                                    ->placeholder('Operator'),
                                Forms\Components\TextInput::make('value')
                                    ->placeholder('Value')
                                    ->visible(fn (Get $get) => ! in_array($get('operator'), ['is_empty', 'is_not_empty', 'changed'])),
                                Forms\Components\Select::make('logic')
                                    ->options([
                                        'AND' => 'AND',
                                        'OR' => 'OR',
                                    ])
                                    ->default('and')
                                    ->visible(fn (Get $get, string $context) => $context === 'edit'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make('Then send data to...')
                    ->description('Choose your automation platform and configure the payload')
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsible()
                    ->schema(function (Get $get) {
                        $modelClass = $get('model_class');

                        return [
                            Forms\Components\Select::make('destination_type')
                                ->label('Destination')
                                ->options(DestinationType::class)
                                ->required()
                                ->live()
                                ->helperText('Zapier, Make, n8n, or any custom webhook endpoint'),
                            Forms\Components\TextInput::make('destination_url')
                                ->label('Webhook URL')
                                ->url()
                                ->required()
                                ->maxLength(2048)
                                ->placeholder('https://hooks.zapier.com/...')
                                ->helperText('Paste the webhook URL from your automation platform'),
                            Forms\Components\Select::make('payload_mode')
                                ->label('Payload Mode')
                                ->options(PayloadMode::class)
                                ->required()
                                ->default(PayloadMode::Summary->value)
                                ->live(),
                            Forms\Components\Select::make('field_mapping')
                                ->label('Include Fields (for Summary mode)')
                                ->multiple()
                                ->searchable()
                                ->hidden(fn (Get $get) => $get('payload_mode')?->value !== PayloadMode::Summary->value)
                                ->dehydratedWhenHidden()
                                ->default([])
                                ->options(function (Get $get) {
                                    $modelClass = $get('model_class');

                                    if (! $modelClass) {
                                        return [];
                                    }

                                    $analyzer = app(FieldSchemaAnalyzer::class);
                                    $attributes = $analyzer->getAttributeNames($modelClass);

                                    return collect($attributes)
                                        ->mapWithKeys(fn ($attr) => [
                                            is_array($attr) ? $attr['name'] : $attr => is_array($attr) ? $attr['name'] : $attr,
                                        ])
                                        ->toArray();
                                })
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('custom_payload_template')
                                ->label('Custom Payload Template (for Custom mode)')
                                ->rows(6)
                                ->placeholder('{"event": "{{ event }}", "data": {{ payload | json }}}')
                                ->hidden(fn (Get $get) => $get('payload_mode')?->value !== PayloadMode::Custom->value)
                                ->dehydratedWhenHidden()
                                ->columnSpanFull()
                                ->helperText('Use {{ field }} for model attributes. Example: {"event": "{{ event }}", "name": "{{ name }}"}'),
                        ];
                    })
                    ->columns(2)
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make('Settings')
                    ->description('Name, security, and behavior for this automation')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable or disable this automation'),
                        Forms\Components\TextInput::make('secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder('Auto-generated if left blank')
                            ->helperText('HMAC secret for payload signing'),
                        Forms\Components\TextInput::make('request_timeout')
                            ->label('Timeout (seconds)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(30),
                        Forms\Components\TextInput::make('max_retries')
                            ->label('Max Retries')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->default(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('model_class')
                    ->label('Model')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        EventEnum::Created => 'success',
                        EventEnum::Updated => 'info',
                        EventEnum::Deleted => 'danger',
                        EventEnum::Restored => 'warning',
                        EventEnum::ForceDeleted => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof EventEnum ? $state->getLabel() : $state),
                Tables\Columns\TextColumn::make('destination_type')
                    ->label('Destination')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state instanceof DestinationType ? $state->getLabel() : $state),
                Tables\Columns\ToggleColumn::make('active')
                    ->label('Active')
                    ->afterStateUpdated(function ($state) {
                        Notification::make()
                            ->title($state ? 'Trigger activated' : 'Trigger deactivated')
                            ->success()
                            ->send();
                    }),
                Tables\Columns\TextColumn::make('last_delivered_at')
                    ->label('Last Delivered')
                    ->dateTime()
                    ->placeholder('Never')
                    ->getStateUsing(fn (AutomationTrigger $record) => $record->deliveries()->latest('created_at')->value('created_at')),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->getStateUsing(function (AutomationTrigger $record) {
                        $stats = $record->successRateLast7Days();

                        if ($stats['total'] === 0) {
                            return 'N/A';
                        }

                        return round(($stats['success'] / $stats['total']) * 100, 1).'%';
                    })
                    ->color(function (AutomationTrigger $record) {
                        $stats = $record->successRateLast7Days();

                        if ($stats['total'] === 0) {
                            return 'gray';
                        }

                        $rate = ($stats['success'] / $stats['total']) * 100;

                        if ($rate >= 90) {
                            return 'success';
                        }

                        if ($rate >= 70) {
                            return 'warning';
                        }

                        return 'danger';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_class')
                    ->label('Model')
                    ->options(fn () => app(ModelDiscoveryService::class)->getAllModels()),
                Tables\Filters\SelectFilter::make('event')
                    ->options(EventEnum::class),
                Tables\Filters\SelectFilter::make('destination_type')
                    ->label('Destination Type')
                    ->options(DestinationType::class),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Active'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (AutomationTrigger $record) {
                        $replica = $record->replicate();
                        $replica->name = $record->name.' (Copy)';
                        $replica->active = false;
                        $replica->secret = AutomationTrigger::generateSecret();
                        $replica->save();

                        Notification::make()
                            ->title('Trigger duplicated')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('view_logs')
                    ->label('Delivery Logs')
                    ->icon('heroicon-o-document-text')
                    ->url('#')
                    ->visible(false),
                Actions\DeleteAction::make()
                    ->before(function (AutomationTrigger $record) {
                        app(DeliveryService::class)->cancelPendingDeliveries($record);
                    }),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
                Actions\BulkAction::make('bulk_enable')
                    ->label('Enable')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Collection $records) {
                        $records->each->update(['active' => true]);

                        Notification::make()
                            ->title('Selected triggers enabled')
                            ->success()
                            ->send();
                    }),
                Actions\BulkAction::make('bulk_disable')
                    ->label('Disable')
                    ->icon('heroicon-o-x-circle')
                    ->action(function (Collection $records) {
                        $records->each->update(['active' => false]);

                        Notification::make()
                            ->title('Selected triggers disabled')
                            ->success()
                            ->send();
                    }),
            ])
            ->poll(config('filament-automation-bridge.ui.polling_interval', '5s'));
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationTriggers::route('/'),
            'create' => CreateAutomationTrigger::route('/create'),
            'edit' => EditAutomationTrigger::route('/{record}/edit'),
            'view' => ViewAutomationTrigger::route('/{record}'),
        ];
    }
}
