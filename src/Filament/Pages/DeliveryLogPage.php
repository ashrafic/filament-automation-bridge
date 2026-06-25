<?php

namespace Ashrafic\FilamentAutomationBridge\Filament\Pages;

use Ashrafic\FilamentAutomationBridge\Enums\DeliverySource;
use Ashrafic\FilamentAutomationBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentAutomationBridge\Models\AutomationDelivery;
use Ashrafic\FilamentAutomationBridge\Services\DeliveryService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DeliveryLogPage extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?int $navigationSort = 81;

    protected string $view = 'filament-automation-bridge::pages.delivery-log';

    protected static ?string $slug = 'automation-bridge/delivery-logs';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('filament-automation-bridge::navigation.group');
    }

    public static function getNavigationIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-document-text';
    }

    public static function getModelLabel(): string
    {
        return __('filament-automation-bridge::labels.delivery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-automation-bridge::labels.deliveries');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('automation_bridge.view_deliveries') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AutomationDelivery::query()->with('trigger')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('trigger.name')
                    ->label(__('filament-automation-bridge::table.trigger'))
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('filament-automation-bridge::table.na')),
                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('filament-automation-bridge::table.model'))
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->description(fn (AutomationDelivery $record) => __('filament-automation-bridge::table.model_id').$record->model_id)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (DeliveryStatus $state) => $state->getColor())
                    ->formatStateUsing(fn (DeliveryStatus $state) => $state->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_status')
                    ->label(__('filament-automation-bridge::table.response'))
                    ->formatStateUsing(function (?int $state) {
                        if ($state === null) {
                            return __('filament-automation-bridge::table.na');
                        }

                        return $state;
                    })
                    ->description(fn (AutomationDelivery $record) => $record->duration_ms ? $record->duration_ms.__('filament-automation-bridge::table.duration_ms') : null)
                    ->color(function (?int $state) {
                        if ($state === null) {
                            return 'gray';
                        }

                        if ($state >= 200 && $state < 300) {
                            return 'success';
                        }

                        if ($state >= 300 && $state < 400) {
                            return 'info';
                        }

                        if ($state >= 400 && $state < 500) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (DeliverySource $state) => match ($state) {
                        DeliverySource::Realtime => 'success',
                        DeliverySource::HistoricalSync => 'info',
                        DeliverySource::Test => 'warning',
                        DeliverySource::ManualRetry => 'gray',
                    })
                    ->formatStateUsing(fn (DeliverySource $state) => $state->getLabel()),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label(__('filament-automation-bridge::table.retries'))
                    ->formatStateUsing(fn (AutomationDelivery $record) => "{$record->retry_count}/{$record->max_retries}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament-automation-bridge::table.delivered_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trigger_id')
                    ->label(__('filament-automation-bridge::table.trigger'))
                    ->relationship('trigger', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament-automation-bridge::table.status'))
                    ->options(DeliveryStatus::class),
                Tables\Filters\SelectFilter::make('http_status_range')
                    ->label(__('filament-automation-bridge::table.http_status'))
                    ->options([
                        '2xx' => __('filament-automation-bridge::enums.http_status_ranges.2xx'),
                        '3xx' => __('filament-automation-bridge::enums.http_status_ranges.3xx'),
                        '4xx' => __('filament-automation-bridge::enums.http_status_ranges.4xx'),
                        '5xx' => __('filament-automation-bridge::enums.http_status_ranges.5xx'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! filled($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            '2xx' => $query->where('http_status', '>=', 200)->where('http_status', '<', 300),
                            '3xx' => $query->where('http_status', '>=', 300)->where('http_status', '<', 400),
                            '4xx' => $query->where('http_status', '>=', 400)->where('http_status', '<', 500),
                            '5xx' => $query->where('http_status', '>=', 500)->where('http_status', '<', 600),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('source')
                    ->label(__('filament-automation-bridge::table.source'))
                    ->options(DeliverySource::class),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Actions\Action::make('retry')
                    ->label(__('filament-automation-bridge::actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-automation-bridge::actions.retry_delivery_modal_heading'))
                    ->modalDescription(__('filament-automation-bridge::actions.retry_delivery_modal_description'))
                    ->visible(fn (AutomationDelivery $record) => $record->canRetry())
                    ->action(function (AutomationDelivery $record) {
                        try {
                            app(DeliveryService::class)->retry($record);

                            Notification::make()
                                ->title(__('filament-automation-bridge::notifications.retry_queued'))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('filament-automation-bridge::notifications.retry_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('view_details')
                    ->label(__('filament-automation-bridge::actions.view_details'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AutomationDelivery $record) => __('filament-automation-bridge::actions.details_modal_heading').$record->id)
                    ->modalContent(fn (AutomationDelivery $record) => view('filament-automation-bridge::pages.delivery-details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-automation-bridge::actions.close'))
                    ->slideOver(),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('bulk_retry')
                    ->label(__('filament-automation-bridge::actions.retry_selected'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->toArray();
                        $queued = app(DeliveryService::class)->bulkRetry($ids);

                        Notification::make()
                            ->title(__('filament-automation-bridge::notifications.bulk_retry_queued', ['count' => $queued]))
                            ->success()
                            ->send();
                    }),
                Actions\DeleteBulkAction::make(),
            ])
            ->poll(config('filament-automation-bridge.ui.polling_interval', '5s'));
    }
}
