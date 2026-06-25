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

    protected static ?string $slug = 'automation-bridge/triggers';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Automation Bridge';
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
                Section::make(__('filament-automation-bridge::form.sections.trigger'))
                    ->description(__('filament-automation-bridge::form.sections.trigger_description'))
                    ->icon('heroicon-o-bolt')
                    ->collapsible()
                    ->schema(function (Get $get) {
                        $schema = [
                            Forms\Components\Select::make('model_class')
                                ->label(__('filament-automation-bridge::form.model'))
                                ->options(fn () => app(ModelDiscoveryService::class)->getAllModels())
                                ->searchable()
                                ->required()
                                ->live()
                                ->disabled(fn (string $operation) => $operation === 'edit' && ! request()->boolean('duplicate'))
                                ->placeholder(__('filament-automation-bridge::form.model_placeholder'))
                                ->helperText(__('filament-automation-bridge::form.model_helper')),
                            Forms\Components\Select::make('trigger_type')
                                ->label(__('filament-automation-bridge::form.trigger_type'))
                                ->options(fn () => app(TriggerManager::class)->options())
                                ->default('model-event')
                                ->required()
                                ->live()
                                ->helperText(__('filament-automation-bridge::form.trigger_type_helper'))
                                ->afterStateUpdated(function (callable $set) {
                                    $set('event', null);
                                    $set('trigger_config', []);
                                }),
                        ];

                        $triggerType = $get('trigger_type') ?? 'model-event';

                        if ($triggerType === 'model-event') {
                            $schema[] = Forms\Components\Select::make('event')
                                ->label(__('filament-automation-bridge::form.event'))
                                ->options(EventEnum::class)
                                ->required()
                                ->live()
                                ->helperText(__('filament-automation-bridge::form.event_helper'));
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
                            ->helperText(__('filament-automation-bridge::form.name_helper'));

                        $schema[] = Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText(__('filament-automation-bridge::form.description_helper'));

                        return $schema;
                    })
                    ->columns(2)
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make(__('filament-automation-bridge::form.sections.conditions'))
                    ->description(__('filament-automation-bridge::form.sections.conditions_description'))
                    ->icon('heroicon-o-funnel')
                    ->collapsible()
                    ->collapsed(fn ($state) => empty(data_get($state, 'conditions')))
                    ->schema([
                        Forms\Components\Repeater::make('conditions')
                            ->label('')
                            ->addActionLabel(__('filament-automation-bridge::form.add_condition'))
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
                                    ->placeholder(__('filament-automation-bridge::form.condition_field_placeholder')),
                                Forms\Components\Select::make('operator')
                                    ->options([
                                        'equals' => __('filament-automation-bridge::enums.condition_operators.equals'),
                                        'not_equals' => __('filament-automation-bridge::enums.condition_operators.not_equals'),
                                        'contains' => __('filament-automation-bridge::enums.condition_operators.contains'),
                                        'greater_than' => __('filament-automation-bridge::enums.condition_operators.greater_than'),
                                        'less_than' => __('filament-automation-bridge::enums.condition_operators.less_than'),
                                        'is_empty' => __('filament-automation-bridge::enums.condition_operators.is_empty'),
                                        'is_not_empty' => __('filament-automation-bridge::enums.condition_operators.is_not_empty'),
                                        'changed' => __('filament-automation-bridge::enums.condition_operators.changed'),
                                        'changed_to' => __('filament-automation-bridge::enums.condition_operators.changed_to'),
                                    ])
                                    ->required()
                                    ->placeholder(__('filament-automation-bridge::form.condition_operator_placeholder')),
                                Forms\Components\TextInput::make('value')
                                    ->placeholder(__('filament-automation-bridge::form.condition_value_placeholder'))
                                    ->visible(fn (Get $get) => ! in_array($get('operator'), ['is_empty', 'is_not_empty', 'changed'])),
                                Forms\Components\Select::make('logic')
                                    ->options([
                                        'AND' => __('filament-automation-bridge::enums.condition_logic.and'),
                                        'OR' => __('filament-automation-bridge::enums.condition_logic.or'),
                                    ])
                                    ->default('and')
                                    ->visible(fn (Get $get, string $context) => $context === 'edit'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make(__('filament-automation-bridge::form.sections.destination'))
                    ->description(__('filament-automation-bridge::form.sections.destination_description'))
                    ->icon('heroicon-o-paper-airplane')
                    ->collapsible()
                    ->schema(function (Get $get) {
                        $modelClass = $get('model_class');

                        return [
                            Forms\Components\Select::make('destination_type')
                                ->label(__('filament-automation-bridge::form.destination_type'))
                                ->options(DestinationType::class)
                                ->required()
                                ->live()
                                ->helperText(__('filament-automation-bridge::form.destination_type_helper')),
                            Forms\Components\TextInput::make('destination_url')
                                ->label(__('filament-automation-bridge::form.destination_url'))
                                ->url()
                                ->required()
                                ->maxLength(2048)
                                ->placeholder(__('filament-automation-bridge::form.destination_url_placeholder'))
                                ->helperText(__('filament-automation-bridge::form.destination_url_helper')),
                            Forms\Components\Select::make('payload_mode')
                                ->label(__('filament-automation-bridge::form.payload_mode'))
                                ->options(PayloadMode::class)
                                ->required()
                                ->default(PayloadMode::Summary->value)
                                ->live(),
                            Forms\Components\Select::make('field_mapping')
                                ->label(__('filament-automation-bridge::form.field_mapping'))
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
                                ->label(__('filament-automation-bridge::form.custom_payload_template'))
                                ->rows(6)
                                ->placeholder(__('filament-automation-bridge::form.custom_payload_template_placeholder'))
                                ->hidden(fn (Get $get) => $get('payload_mode')?->value !== PayloadMode::Custom->value)
                                ->dehydratedWhenHidden()
                                ->columnSpanFull()
                                ->helperText(__('filament-automation-bridge::form.custom_payload_template_helper')),
                        ];
                    })
                    ->columns(2)
                    ->extraAttributes(['class' => '!mb-6']),

                Section::make(__('filament-automation-bridge::form.sections.settings'))
                    ->description(__('filament-automation-bridge::form.sections.settings_description'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label(__('filament-automation-bridge::form.active'))
                            ->default(true)
                            ->helperText(__('filament-automation-bridge::form.active_helper')),
                        Forms\Components\Select::make('http_method')
                            ->label(__('filament-automation-bridge::form.http_method'))
                            ->options([
                                'GET' => __('filament-automation-bridge::enums.http_methods.GET'),
                                'POST' => __('filament-automation-bridge::enums.http_methods.POST'),
                                'PUT' => __('filament-automation-bridge::enums.http_methods.PUT'),
                                'PATCH' => __('filament-automation-bridge::enums.http_methods.PATCH'),
                                'DELETE' => __('filament-automation-bridge::enums.http_methods.DELETE'),
                            ])
                            ->default('POST')
                            ->required()
                            ->helperText(__('filament-automation-bridge::form.http_method_helper')),
                        Forms\Components\TextInput::make('secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder(function (Get $get) {
                                $type = $get('destination_type');
                                $n8nMode = $get('trigger_config.n8n_auth_mode');

                                if ($type?->value === 'make') {
                                    return __('filament-automation-bridge::form.secret_placeholder_make');
                                }

                                if ($type?->value === 'n8n') {
                                    return match ($n8nMode) {
                                        'basic' => __('filament-automation-bridge::form.secret_placeholder_n8n_basic'),
                                        'bearer' => __('filament-automation-bridge::form.secret_placeholder_n8n_bearer'),
                                        default => __('filament-automation-bridge::form.secret_placeholder_n8n_header'),
                                    };
                                }

                                return __('filament-automation-bridge::form.secret_placeholder');
                            })
                            ->helperText(function (Get $get) {
                                $type = $get('destination_type');
                                $n8nMode = $get('trigger_config.n8n_auth_mode');

                                if ($type?->value === 'make') {
                                    return __('filament-automation-bridge::form.secret_helper_make');
                                }

                                if ($type?->value === 'n8n') {
                                    return match ($n8nMode) {
                                        'basic' => __('filament-automation-bridge::form.secret_helper_n8n_basic'),
                                        'bearer' => __('filament-automation-bridge::form.secret_helper_n8n_bearer'),
                                        default => __('filament-automation-bridge::form.secret_helper_n8n_header'),
                                    };
                                }

                                return __('filament-automation-bridge::form.secret_helper_default');
                            }),
                        Forms\Components\Select::make('trigger_config.n8n_auth_mode')
                            ->label(__('filament-automation-bridge::form.n8n_auth_mode'))
                            ->options([
                                'header' => __('filament-automation-bridge::form.n8n_auth_mode_header'),
                                'basic' => __('filament-automation-bridge::form.n8n_auth_mode_basic'),
                                'bearer' => __('filament-automation-bridge::form.n8n_auth_mode_bearer'),
                            ])
                            ->default('header')
                            ->live()
                            ->visible(fn (Get $get) => $get('destination_type')?->value === 'n8n')
                            ->helperText(__('filament-automation-bridge::form.n8n_auth_mode_helper')),
                        Forms\Components\TextInput::make('trigger_config.n8n_header_name')
                            ->label(__('filament-automation-bridge::form.n8n_header_name'))
                            ->default('X-Api-Key')
                            ->visible(fn (Get $get) => in_array($get('trigger_config.n8n_auth_mode'), ['header', null]) && $get('destination_type')?->value === 'n8n')
                            ->helperText(__('filament-automation-bridge::form.n8n_header_name_helper')),
                        Forms\Components\TextInput::make('request_timeout')
                            ->label(__('filament-automation-bridge::form.request_timeout'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(30),
                        Forms\Components\TextInput::make('max_retries')
                            ->label(__('filament-automation-bridge::form.max_retries'))
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
                    ->label(__('filament-automation-bridge::table.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('model_class')
                    ->label(__('filament-automation-bridge::table.model'))
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
                    ->label(__('filament-automation-bridge::table.destination'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state instanceof DestinationType ? $state->getLabel() : $state),
                Tables\Columns\ToggleColumn::make('active')
                    ->label(__('filament-automation-bridge::table.active'))
                    ->afterStateUpdated(function ($state) {
                        Notification::make()
                            ->title($state ? __('filament-automation-bridge::notifications.activated') : __('filament-automation-bridge::notifications.deactivated'))
                            ->success()
                            ->send();
                    }),
                Tables\Columns\TextColumn::make('last_delivered_at')
                    ->label(__('filament-automation-bridge::table.last_delivered'))
                    ->dateTime()
                    ->placeholder(__('filament-automation-bridge::table.never'))
                    ->getStateUsing(fn (AutomationTrigger $record) => $record->deliveries()->latest('created_at')->value('created_at')),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label(__('filament-automation-bridge::table.success_rate'))
                    ->getStateUsing(function (AutomationTrigger $record) {
                        $stats = $record->successRateLast7Days();

                        if ($stats['total'] === 0) {
                            return __('filament-automation-bridge::table.na');
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
                    ->label(__('filament-automation-bridge::table.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_class')
                    ->label(__('filament-automation-bridge::table.model'))
                    ->options(fn () => app(ModelDiscoveryService::class)->getAllModels()),
                Tables\Filters\SelectFilter::make('event')
                    ->options(EventEnum::class),
                Tables\Filters\SelectFilter::make('destination_type')
                    ->label(__('filament-automation-bridge::table.destination_type'))
                    ->options(DestinationType::class),
                Tables\Filters\TernaryFilter::make('active')
                    ->label(__('filament-automation-bridge::table.active')),
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
                    ->action(function (AutomationTrigger $record, Actions\Action $action) {
                        $replica = $record->replicate();
                        $replica->name = $record->name.__('filament-automation-bridge::actions.copy');
                        $replica->active = false;
                        $replica->secret = AutomationTrigger::generateSecret();
                        $replica->save();

                        Notification::make()
                            ->title(__('filament-automation-bridge::notifications.duplicated'))
                            ->success()
                            ->send();

                        return redirect(static::getUrl('edit', ['record' => $replica]).'?duplicate=1');
                    }),
                Actions\Action::make('view_logs')
                    ->label(__('filament-automation-bridge::actions.view_logs'))
                    ->icon('heroicon-o-document-text')
                    ->url('#')
                    ->visible(false),
                Actions\DeleteAction::make()
                    ->before(function (AutomationTrigger $record) {
                        app(DeliveryService::class)->cancelPendingDeliveries($record);
                    }),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
                Actions\BulkAction::make('bulk_enable')
                    ->label(__('filament-automation-bridge::actions.enable'))
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Collection $records) {
                        $records->each->update(['active' => true]);

                        Notification::make()
                            ->title(__('filament-automation-bridge::notifications.enabled'))
                            ->success()
                            ->send();
                    }),
                Actions\BulkAction::make('bulk_disable')
                    ->label(__('filament-automation-bridge::actions.disable'))
                    ->icon('heroicon-o-x-circle')
                    ->action(function (Collection $records) {
                        $records->each->update(['active' => false]);

                        Notification::make()
                            ->title(__('filament-automation-bridge::notifications.disabled'))
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
