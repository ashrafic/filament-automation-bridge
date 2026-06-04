<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Resources;

use Ashrafic\FilamentWebhookBridge\Enums\DestinationType;
use Ashrafic\FilamentWebhookBridge\Enums\EventEnum;
use Ashrafic\FilamentWebhookBridge\Enums\PayloadMode;
use Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages\CreateWebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages\EditWebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages\ListWebhookTriggers;
use Ashrafic\FilamentWebhookBridge\Filament\Resources\Pages\ViewWebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Models\WebhookTrigger;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
use Ashrafic\FilamentWebhookBridge\Services\FieldSchemaAnalyzer;
use Ashrafic\FilamentWebhookBridge\Services\ModelDiscoveryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WebhookTriggerResource extends Resource
{
    protected static ?string $model = WebhookTrigger::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-webhook-bridge.ui.navigation_group', 'Integrations');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-webhook-bridge.ui.navigation_sort', 80);
    }

    public static function getModelLabel(): string
    {
        return 'Webhook Trigger';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Webhook Triggers';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('webhook_bridge.view_triggers') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trigger Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('model_class')
                            ->label('Model')
                            ->options(fn () => app(ModelDiscoveryService::class)->getAllModels())
                            ->searchable()
                            ->required()
                            ->live()
                            ->placeholder('Select a model'),
                        Forms\Components\Select::make('event')
                            ->options(EventEnum::class)
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('destination_type')
                            ->label('Destination Type')
                            ->options(DestinationType::class)
                            ->required(),
                        Forms\Components\TextInput::make('destination_url')
                            ->label('Destination URL')
                            ->url()
                            ->required()
                            ->maxLength(2048),
                        Forms\Components\Toggle::make('active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payload Configuration')
                    ->schema([
                        Forms\Components\Select::make('payload_mode')
                            ->label('Payload Mode')
                            ->options(PayloadMode::class)
                            ->required()
                            ->default(PayloadMode::Summary->value)
                            ->live(),
                        Forms\Components\Select::make('field_mapping')
                            ->label('Fields')
                            ->multiple()
                            ->options(function (Forms\Get $get) {
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
                            ->visible(fn (Forms\Get $get) => $get('payload_mode') === PayloadMode::Summary->value)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('custom_payload_template')
                            ->label('Custom Payload Template')
                            ->rows(6)
                            ->visible(fn (Forms\Get $get) => $get('payload_mode') === PayloadMode::Custom->value)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Conditions (Optional)')
                    ->schema([
                        Forms\Components\Repeater::make('conditions')
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->options(function (Forms\Get $get) {
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
                                    ->required(),
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
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->visible(fn (Forms\Get $get) => ! in_array($get('operator'), ['is_empty', 'is_not_empty', 'changed'])),
                                Forms\Components\Select::make('log')
                                    ->options([
                                        'and' => 'AND',
                                        'or' => 'OR',
                                    ])
                                    ->default('and')
                                    ->visible(fn (Forms\Get $get, string $context) => $context === 'edit'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(fn ($state) => empty($state))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($state) => empty(data_get($state, 'conditions'))),

                Forms\Components\Section::make('Security & Settings')
                    ->schema([
                        Forms\Components\TextInput::make('secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            ->placeholder('Leave blank to auto-generate'),
                        Forms\Components\TextInput::make('webhook_timeout')
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
                    ->columns(3),
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
                Tables\Columns\BadgeColumn::make('event')
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
                Tables\Columns\BadgeColumn::make('destination_type')
                    ->label('Destination')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state instanceof DestinationType ? $state->getLabel() : $state),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('last_delivered_at')
                    ->label('Last Delivered')
                    ->dateTime()
                    ->placeholder('Never')
                    ->getStateUsing(fn (WebhookTrigger $record) => $record->deliveries()->latest('created_at')->value('created_at')),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->getStateUsing(function (WebhookTrigger $record) {
                        $stats = $record->successRateLast7Days();

                        if ($stats['total'] === 0) {
                            return 'N/A';
                        }

                        return round(($stats['success'] / $stats['total']) * 100, 1).'%';
                    })
                    ->color(function (WebhookTrigger $record) {
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (WebhookTrigger $record) => $record->active ? 'Deactivate' : 'Activate')
                    ->icon(fn (WebhookTrigger $record) => $record->active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function (WebhookTrigger $record) {
                        $record->update(['active' => ! $record->active]);

                        Notification::make()
                            ->title($record->active ? 'Trigger activated' : 'Trigger deactivated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (WebhookTrigger $record) {
                        $replica = $record->replicate();
                        $replica->name = $record->name.' (Copy)';
                        $replica->active = false;
                        $replica->secret = WebhookTrigger::generateSecret();
                        $replica->save();

                        Notification::make()
                            ->title('Trigger duplicated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_logs')
                    ->label('Delivery Logs')
                    ->icon('heroicon-o-document-text')
                    ->url('#')
                    ->visible(false),
                Tables\Actions\DeleteAction::make()
                    ->before(function (WebhookTrigger $record) {
                        app(DeliveryService::class)->cancelPendingDeliveries($record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\BulkAction::make('bulk_enable')
                    ->label('Enable')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Collection $records) {
                        $records->each->update(['active' => true]);

                        Notification::make()
                            ->title('Selected triggers enabled')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('bulk_disable')
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
            ->poll(config('filament-webhook-bridge.ui.polling_interval', '5s'));
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookTriggers::route('/'),
            'create' => CreateWebhookTrigger::route('/create'),
            'edit' => EditWebhookTrigger::route('/{record}/edit'),
            'view' => ViewWebhookTrigger::route('/{record}'),
        ];
    }
}
