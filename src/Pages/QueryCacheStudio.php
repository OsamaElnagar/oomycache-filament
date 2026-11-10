<?php

declare(strict_types=1);

namespace Oomycache\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Oomycache\Oomycache\Cache\QueryCacheEngine;
use Oomycache\Oomycache\Contracts\VersionStore;
use Oomycache\Oomycache\Oomycache;
use Throwable;
use UnitEnum;

/**
 * Filament cache-management studio for the OoMyCache package.
 *
 * Read-only status/observability plus two operator actions:
 * - invalidate one cacheable model (O(1) version-token bump);
 * - clear the whole query cache (global token bump).
 *
 * Every read is bounded: the model list comes from the package's cacheable
 * model discovery (no DB query) and one version-store read per model.
 */
class QueryCacheStudio extends Page
{
    protected static ?string $slug = 'query-cache';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $title = 'Query Cache';

    protected string $view = 'oomycache-filament::pages.query-cache-studio';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $statusMemo = null;

    /**
     * @var array<string, array{table: string, token: string, ttl: int, enabled: bool}>|null
     */
    private ?array $modelsMemo = null;

    public static function getNavigationLabel(): string
    {
        return (string) config('oomycache.filament.dashboard.navigation_label', 'Query Cache');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('oomycache.filament.dashboard.navigation_group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('oomycache.filament.dashboard.navigation_sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $gate = config('oomycache.filament.dashboard.can_view');

        if (is_string($gate) && $gate !== '' && Gate::has($gate)) {
            return Gate::allows($gate);
        }

        return true;
    }

    /**
     * Destructive actions (model invalidation / global purge) are deny-by-default.
     *
     * Configure `oomycache.filament.dashboard.can_purge` with a Gate name and
     * define that gate for the users who may purge caches. Without a configured
     * gate the action buttons are hidden and the closures refuse to run.
     */
    public static function canPurge(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $gate = config('oomycache.filament.dashboard.can_purge');

        if (! is_string($gate) || $gate === '') {
            return false;
        }

        return Gate::has($gate) && Gate::allows($gate);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('invalidateSelectedModel')
                ->label('Invalidate selected model')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => static::canPurge())
                ->schema([
                    Select::make('model')
                        ->label('Cacheable model')
                        ->options($this->modelOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (! static::canPurge()) {
                        Notification::make()
                            ->title('Not authorized')
                            ->body('You do not have permission to purge OoMyCache entries.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $model = $data['model'] ?? null;

                    if (! is_string($model) || ! class_exists($model) || ! $this->isCacheableModel($model)) {
                        Notification::make()
                            ->title('Unknown cacheable model')
                            ->body('Select one of the discovered cacheable models.')
                            ->warning()
                            ->send();

                        return;
                    }

                    /** @var class-string $model */
                    try {
                        app(Oomycache::class)->flush([$model]);
                        $this->forgetMemoizedStatus();

                        Notification::make()
                            ->title('Model cache invalidated')
                            ->body(class_basename($model).' version token bumped (O(1) invalidation).')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Invalidation failed')
                            ->body('See the application log for details.')
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('clearQueryCache')
                ->label('Clear query cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => static::canPurge())
                ->requiresConfirmation()
                ->modalDescription('Bumps the global version token. Every cached query becomes unreachable immediately — O(1), no key scans.')
                ->action(function (): void {
                    if (! static::canPurge()) {
                        Notification::make()
                            ->title('Not authorized')
                            ->body('You do not have permission to purge the OoMyCache cache.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        app(Oomycache::class)->clear();
                        $this->forgetMemoizedStatus();

                        Notification::make()
                            ->title('Query cache cleared')
                            ->body('Global version token bumped; every cached query is unreachable.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Clear failed')
                            ->body('See the application log for details.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function bridgeAvailable(): bool
    {
        return class_exists(Oomycache::class) && class_exists(QueryCacheEngine::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        if ($this->statusMemo !== null) {
            return $this->statusMemo;
        }

        if (! $this->bridgeAvailable()) {
            return $this->statusMemo = [];
        }

        try {
            return $this->statusMemo = app(Oomycache::class)->status();
        } catch (Throwable) {
            return $this->statusMemo = [];
        }
    }

    /**
     * Effective engine status (driver, store capabilities, payload mode, TTL,
     * payload/global version tokens).
     *
     * @return array<int, array{label: string, value: string, badge: string}>
     */
    public function statusRows(): array
    {
        $status = $this->status();

        if ($status === []) {
            return [];
        }

        $capabilities = is_array($status['capabilities'] ?? null) ? $status['capabilities'] : [];
        $tenant = $status['tenant'] ?? null;
        $enabled = (bool) ($status['enabled'] ?? false);
        $taggable = (bool) ($capabilities['taggable'] ?? false);
        $lockable = (bool) ($capabilities['lockable'] ?? false);

        return [
            ['label' => 'Enabled', 'value' => $enabled ? 'yes' : 'no', 'badge' => $enabled ? 'success' : 'gray'],
            ['label' => 'Cache store', 'value' => (string) ($status['store'] ?? '—'), 'badge' => 'info'],
            ['label' => 'Store class', 'value' => isset($capabilities['store']) ? class_basename((string) $capabilities['store']) : '—', 'badge' => 'gray'],
            ['label' => 'Tag support', 'value' => $taggable ? 'yes' : 'no', 'badge' => $taggable ? 'success' : 'gray'],
            ['label' => 'Lock support', 'value' => $lockable ? 'yes' : 'no', 'badge' => $lockable ? 'success' : 'gray'],
            ['label' => 'Payload mode', 'value' => (string) ($status['payload_mode'] ?? '—'), 'badge' => 'info'],
            ['label' => 'Default TTL', 'value' => ((int) ($status['ttl'] ?? 0)).' s · jitter '.round(((float) ($status['ttl_jitter'] ?? 0)) * 100).'%', 'badge' => 'gray'],
            ['label' => 'Key prefix', 'value' => (string) ($status['prefix'] ?? '—'), 'badge' => 'gray'],
            ['label' => 'Payload version', 'value' => (string) ($status['version'] ?? '—'), 'badge' => 'gray'],
            ['label' => 'Global version token', 'value' => $this->shortToken((string) ($status['global_token'] ?? '—')), 'badge' => 'warning'],
            ['label' => 'Tenant segments', 'value' => is_array($tenant) && $tenant !== [] ? Str::limit((string) json_encode($tenant), 80) : 'none', 'badge' => 'gray'],
        ];
    }

    /**
     * Hit / miss observability flags (OoMyCache emits hit/miss/invalidation
     * events; the optional stats snapshot is opt-in).
     *
     * @return array<int, array{label: string, value: string, badge: string}>
     */
    public function metricsRows(): array
    {
        $eventsEnabled = (bool) config('oomycache.events.enabled', true);
        $hits = $eventsEnabled && (bool) config('oomycache.events.hits', true);
        $misses = $eventsEnabled && (bool) config('oomycache.events.misses', true);
        $invalidations = $eventsEnabled && (bool) config('oomycache.events.invalidations', true);
        $stats = (bool) config('oomycache.stats.enabled', false);

        $rows = [
            ['label' => 'Hit events', 'value' => $hits ? 'enabled' : 'disabled', 'badge' => $hits ? 'success' : 'gray'],
            ['label' => 'Miss events', 'value' => $misses ? 'enabled' : 'disabled', 'badge' => $misses ? 'success' : 'gray'],
            ['label' => 'Invalidation events', 'value' => $invalidations ? 'enabled' : 'disabled', 'badge' => $invalidations ? 'success' : 'gray'],
            ['label' => 'Stats counters', 'value' => $stats ? 'enabled' : 'disabled — observe events', 'badge' => $stats ? 'success' : 'gray'],
        ];

        $snapshot = $this->status()['stats'] ?? null;

        if (is_array($snapshot) && (bool) ($snapshot['enabled'] ?? false)) {
            $rows[] = ['label' => 'Hits (window)', 'value' => (string) ($snapshot['hits'] ?? 0), 'badge' => 'success'];
            $rows[] = ['label' => 'Misses (window)', 'value' => (string) ($snapshot['misses'] ?? 0), 'badge' => 'warning'];
            $rows[] = ['label' => 'Bypasses (window)', 'value' => (string) ($snapshot['bypasses'] ?? 0), 'badge' => 'gray'];
            $rows[] = ['label' => 'Invalidations (window)', 'value' => (string) ($snapshot['invalidations'] ?? 0), 'badge' => 'danger'];
            $rows[] = ['label' => 'Clears (window)', 'value' => (string) ($snapshot['clears'] ?? 0), 'badge' => 'danger'];
        }

        return $rows;
    }

    /**
     * Discovered cacheable models plus one version-store read per model.
     *
     * @return array<string, array{table: string, token: string, ttl: int, enabled: bool}>
     */
    public function cacheableModels(): array
    {
        if ($this->modelsMemo !== null) {
            return $this->modelsMemo;
        }

        if (! $this->bridgeAvailable()) {
            return $this->modelsMemo = [];
        }

        try {
            $engine = app(QueryCacheEngine::class);
            $versions = app(VersionStore::class);
            $models = [];

            foreach ($engine->cacheableModels() as $class) {
                /** @var Model $instance */
                $instance = new $class;
                $options = $engine->optionsFor($class);
                $table = (string) $instance->getTable();

                $models[$class] = [
                    'table' => $table,
                    'token' => $versions->get($table, $instance->getConnection()->getName()),
                    'ttl' => (int) ($options['ttl'] ?? 0),
                    'enabled' => (bool) ($options['enabled'] ?? false),
                ];
            }

            return $this->modelsMemo = $models;
        } catch (Throwable) {
            return $this->modelsMemo = [];
        }
    }

    /**
     * @return array<string, string>
     */
    public function modelOptions(): array
    {
        $options = [];

        foreach ($this->cacheableModels() as $class => $model) {
            $options[$class] = class_basename($class).' ('.$model['table'].')';
        }

        return $options;
    }

    private function isCacheableModel(string $model): bool
    {
        if (! class_exists($model)) {
            return false;
        }

        try {
            return $this->bridgeAvailable() && app(QueryCacheEngine::class)->isCacheableModel($model);
        } catch (Throwable) {
            return false;
        }
    }

    private function shortToken(string $token): string
    {
        if ($token === '' || $token === '—') {
            return '—';
        }

        return strlen($token) > 18 ? substr($token, 0, 18).'…' : $token;
    }

    private function forgetMemoizedStatus(): void
    {
        $this->statusMemo = null;
        $this->modelsMemo = null;
    }
}
