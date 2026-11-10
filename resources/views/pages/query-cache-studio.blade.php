<x-filament-panels::page>
    @php
        $bridgeAvailable = $this->bridgeAvailable();
        $statusRows = $this->statusRows();
        $metricsRows = $this->metricsRows();
        $models = $this->cacheableModels();
    @endphp

    @if (! $bridgeAvailable)
        <x-filament::section
            heading="OoMyCache not installed"
            description="The query cache package could not be detected in this application."
            icon="heroicon-o-exclamation-triangle"
        >
            <p style="font-size: 0.875rem;">
                Install <strong>osamaelnagar/oomycache</strong> and add the
                <strong>HasQueryCache</strong> trait to the models you want cached.
            </p>
        </x-filament::section>
    @else
        <x-filament::section
            heading="Cache status"
            description="Effective OoMyCache configuration read from the engine status array."
            icon="heroicon-o-circle-stack"
        >
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem;">
                @foreach ($statusRows as $row)
                    <div>
                        <p style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.6;">
                            {{ $row['label'] }}
                        </p>
                        <div style="margin-top: 0.35rem;">
                            <x-filament::badge :color="$row['badge']">
                                {{ $row['value'] }}
                            </x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Hit / miss instrumentation"
            description="OoMyCache emits hit, miss and invalidation events; the optional stats snapshot is opt-in."
            icon="heroicon-o-signal"
        >
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem;">
                @foreach ($metricsRows as $row)
                    <div>
                        <p style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.6;">
                            {{ $row['label'] }}
                        </p>
                        <div style="margin-top: 0.35rem;">
                            <x-filament::badge :color="$row['badge']">
                                {{ $row['value'] }}
                            </x-filament::badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Cacheable models"
            description="Models discovered by OoMyCache (trait or contract) with their current per-table version tokens."
            icon="heroicon-o-cube"
        >
            @if ($models === [])
                <p style="font-size: 0.875rem; opacity: 0.7;">
                    No cacheable models detected. Add the HasQueryCache trait to an Eloquent model.
                </p>
            @else
                <div>
                    @foreach ($models as $class => $model)
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.75rem 0; {{ $loop->first ? '' : 'border-top: 1px solid rgba(128, 128, 128, 0.18);' }}">
                            <div>
                                <p style="font-size: 0.9rem; font-weight: 600;">
                                    {{ class_basename($class) }}
                                </p>
                                <p style="font-size: 0.75rem; opacity: 0.65;">
                                    {{ $class }} · table: {{ $model['table'] }}
                                </p>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                                <x-filament::badge :color="$model['enabled'] ? 'success' : 'gray'">
                                    {{ $model['enabled'] ? 'enabled' : 'disabled' }}
                                </x-filament::badge>
                                <x-filament::badge color="info">
                                    ttl {{ $model['ttl'] }}s
                                </x-filament::badge>
                                <x-filament::badge color="warning">
                                    token {{ $model['token'] }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
