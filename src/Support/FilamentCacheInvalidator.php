<?php

declare(strict_types=1);

namespace Oomycache\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Events\ActionCalled;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Oomycache\Oomycache\Concerns\HasQueryCache;
use Oomycache\Oomycache\Contracts\QueryCacheable;
use Oomycache\Oomycache\Oomycache;
use Throwable;

/**
 * Filament "ActionCalled" → OoMyCache invalidation bridge.
 *
 * Filament actions such as attach / detach / dissociate / associate / reorder /
 * import, plus the mass branches of bulk delete / force-delete / restore, write
 * through query builders or pivot tables that fire no Eloquent model events.
 * This listener flushes the version namespaces of the affected cacheable models
 * after such an action, without ever breaking the panel action itself.
 *
 * Registration is intentionally explicit: call
 *
 *     FilamentCacheInvalidator::register();
 *
 * by the bridge service provider. register() is idempotent and no-ops
 * when Filament or OoMyCache is not installed.
 */
final class FilamentCacheInvalidator
{
    /**
     * Filament default action names likely to bypass Eloquent model events.
     *
     * @var list<string>
     */
    private const FLUSH_AFTER_ACTION_NAMES = [
        'attach',
        'associate',
        'detach',
        'dissociate',
        'reorder',
        'import',
        'delete',
        'force-delete',
        'restore',
    ];

    /**
     * Per-record actions that the core model/pivot events already invalidate for
     * cacheable models. Flushing them again from the bridge would duplicate the
     * audit/stats notification for one logical write.
     *
     * @var list<string>
     */
    private const CORE_HANDLED_ACTION_NAMES = [
        'attach',
        'detach',
        'delete',
        'force-delete',
        'restore',
    ];

    private const REGISTERED_KEY = 'oomycache.filament-invalidator.registered';

    public static function register(): void
    {
        if (app()->bound(self::REGISTERED_KEY)) {
            return;
        }

        if (! class_exists(ActionCalled::class) || ! class_exists(Oomycache::class)) {
            return;
        }

        app()->instance(self::REGISTERED_KEY, true);

        app()->singleton(self::class);

        Event::listen(ActionCalled::class, static function (mixed $payload): void {
            $action = match (true) {
                $payload instanceof ActionCalled => $payload->getAction(),
                $payload instanceof Action => $payload,
                default => null,
            };

            if ($action === null) {
                return;
            }

            try {
                app(self::class)->handle($action);
            } catch (Throwable $exception) {
                // Cache bookkeeping must never break a panel action.
                if (function_exists('report')) {
                    report($exception);
                }
            }
        });
    }

    public function handle(Action $action): void
    {
        try {
            if (! $this->shouldFlush($action)) {
                return;
            }

            $models = array_values(array_filter(
                $this->resolveModels($action),
                fn (string $modelClass): bool => ! $this->coreHandles($action, $modelClass),
            ));

            foreach ($models as $modelClass) {
                $this->flushModel($modelClass);
            }
        } catch (Throwable $exception) {
            if (function_exists('report')) {
                report($exception);
            }
        }
    }

    /**
     * Cacheable models already invalidate on their own Eloquent events and on
     * caching belongsToMany/morphToMany pivot operations. Only fall back to a
     * bridge flush for actions the core cannot see.
     */
    private function coreHandles(Action $action, string $modelClass): bool
    {
        if (! in_array($action->getName(), self::CORE_HANDLED_ACTION_NAMES, true)) {
            return false;
        }

        return $this->isCacheableModel($modelClass);
    }

    private function shouldFlush(Action $action): bool
    {
        // Every bulk action may use the event-bypassing mass branch
        // (fetchSelectedRecords(false) + getSelectedRecordsQuery()).
        if ($action instanceof BulkAction) {
            return true;
        }

        return in_array($action->getName(), self::FLUSH_AFTER_ACTION_NAMES, true);
    }

    /**
     * Resolve the affected model(s) defensively: action record → action model →
     * livewire table model.
     *
     * @return list<class-string<Model>>
     */
    private function resolveModels(Action $action): array
    {
        $models = [];

        try {
            if ($action->hasRecord()) {
                $record = $action->getRecord();

                if ($record instanceof Model) {
                    $models[] = $record::class;
                }
            }
        } catch (Throwable) {
            // Record key not resolvable: fall through to action/table model.
        }

        try {
            $model = $action->getModel();

            if (is_string($model) && class_exists($model)) {
                $models[] = $model;
            }
        } catch (Throwable) {
            // Ignore: defensive resolution only.
        }

        try {
            $livewire = $action->getLivewire();

            if ($livewire instanceof HasTable) {
                $model = $livewire->getTable()->getModel();

                if (is_string($model) && class_exists($model)) {
                    $models[] = $model;
                }
            }
        } catch (Throwable) {
            // Ignore: defensive resolution only.
        }

        return array_values(array_unique($models));
    }

    private function flushModel(string $modelClass): void
    {
        if (! $this->isCacheableModel($modelClass)) {
            return;
        }

        /** @var class-string<Model> $modelClass */
        app(Oomycache::class)->flush([$modelClass]);
    }

    private function isCacheableModel(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        $usesTrait = in_array(HasQueryCache::class, class_uses_recursive($modelClass), true);
        $implementsContract = is_subclass_of($modelClass, QueryCacheable::class);

        return $usesTrait || $implementsContract;
    }
}
