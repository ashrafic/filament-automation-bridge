<?php

namespace Ashrafic\FilamentWebhookBridge\Filament\Pages;

use Ashrafic\FilamentWebhookBridge\Enums\DeliverySource;
use Ashrafic\FilamentWebhookBridge\Enums\DeliveryStatus;
use Ashrafic\FilamentWebhookBridge\Models\WebhookDelivery;
use Ashrafic\FilamentWebhookBridge\Services\DeliveryService;
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

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 81;

    protected static string $view = 'filament-webhook-bridge::pages.delivery-log';

    protected static ?string $slug = 'webhook-bridge/delivery-logs';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-webhook-bridge.ui.navigation_group', 'Integrations');
    }

    public static function getModelLabel(): string
    {
        return 'Delivery Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Delivery Logs';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('webhook_bridge.view_deliveries') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(WebhookDelivery::query()->with('trigger')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('trigger.name')
                    ->label('Trigger')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model_id')
                    ->label('Model ID')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->badge()
                    ->color(fn (DeliveryStatus $state) => $state->getColor())
                    ->formatStateUsing(fn (DeliveryStatus $state) => $state->getLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_status')
                    ->label('HTTP Status')
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
                Tables\Columns\BadgeColumn::make('source')
                    ->badge()
                    ->color(fn (DeliverySource $state) => match ($state) {
                        DeliverySource::Realtime => 'success',
                        DeliverySource::HistoricalSync => 'info',
                        DeliverySource::Test => 'warning',
                        DeliverySource::ManualRetry => 'gray',
                    })
                    ->formatStateUsing(fn (DeliverySource $state) => $state->getLabel()),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->formatStateUsing(fn (WebhookDelivery $record) => "{$record->retry_count}/{$record->max_retries}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state ? $state.' ms' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Delivered At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trigger_id')
                    ->label('Trigger')
                    ->relationship('trigger', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::class),
                Tables\Filters\SelectFilter::make('http_status_range')
                    ->label('HTTP Status')
                    ->options([
                        '2xx' => '2xx (Success)',
                        '3xx' => '3xx (Redirect)',
                        '4xx' => '4xx (Client Error)',
                        '5xx' => '5xx (Server Error)',
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
                    ->label('Source')
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
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Delivery')
                    ->modalDescription('This will create a new delivery attempt.')
                    ->visible(fn (WebhookDelivery $record) => $record->canRetry())
                    ->action(function (WebhookDelivery $record) {
                        try {
                            app(DeliveryService::class)->retry($record);

                            Notification::make()
                                ->title('Delivery retry queued')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Retry failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (WebhookDelivery $record) => "Delivery #{$record->id}")
                    ->modalContent(fn (WebhookDelivery $record) => view('filament-webhook-bridge::pages.delivery-details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->slideOver(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_retry')
                    ->label('Retry Selected')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->toArray();
                        $queued = app(DeliveryService::class)->bulkRetry($ids);

                        Notification::make()
                            ->title("{$queued} delivery(s) retry queued")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->poll(config('filament-webhook-bridge.ui.polling_interval', '5s'));
    }
}
