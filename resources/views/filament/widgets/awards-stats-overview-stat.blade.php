@php
    $url = $getUrl();
@endphp

<div
    {{
        $getExtraAttributeBag()
            ->class([
                'fi-wi-stats-overview-stat',
            ])
    }}
>
    <div class="flex h-full items-center justify-between gap-4 px-1 py-1">
        @if ($url)
            <a
                href="{{ $url }}"
                @if ($shouldOpenUrlInNewTab())
                    target="_blank" rel="noopener noreferrer"
                @endif
                class="min-w-0 flex-1 cursor-pointer no-underline"
            >
                <span class="fi-wi-stats-overview-stat-label-ctn block">
                    <span class="fi-wi-stats-overview-stat-label block">
                        {{ $getLabel() }}
                    </span>
                </span>

                @if (filled($value = $getValue()))
                    <span class="fi-wi-stats-overview-stat-value block">
                        {{ $value }}
                    </span>
                @else
                    <span class="fi-wi-stats-overview-stat-placeholder block">
                        {{ $getPlaceholder() }}
                    </span>
                @endif

                @if ($description = $getDescription())
                    <span class="fi-wi-stats-overview-stat-description block text-gray-500 dark:text-gray-400">
                        {{ $description }}
                    </span>
                @endif
            </a>
        @else
            <span class="min-w-0 flex-1">
                <span class="fi-wi-stats-overview-stat-label-ctn block">
                    <span class="fi-wi-stats-overview-stat-label block">
                        {{ $getLabel() }}
                    </span>
                </span>

                @if (filled($value = $getValue()))
                    <span class="fi-wi-stats-overview-stat-value block">
                        {{ $value }}
                    </span>
                @else
                    <span class="fi-wi-stats-overview-stat-placeholder block">
                        {{ $getPlaceholder() }}
                    </span>
                @endif

                @if ($description = $getDescription())
                    <span class="fi-wi-stats-overview-stat-description block text-gray-500 dark:text-gray-400">
                        {{ $description }}
                    </span>
                @endif
            </span>
        @endif

        <div x-data="{ imageFailed: false }" class="size-20 shrink-0">
            @if (filled($awardImageUrl))
                <button
                    type="button"
                    class="flex size-20 cursor-zoom-in items-center justify-center border-0 bg-transparent p-0"
                    aria-label="{{ __('View image: :name', ['name' => $getLabel()]) }}"
                    x-on:click="$refs.awardImageDialog.showModal()"
                >
                    <img
                        x-show="! imageFailed"
                        src="{{ $awardImageUrl }}"
                        alt="{{ $getLabel() }}"
                        loading="lazy"
                        class="size-20 object-contain"
                        x-on:error="imageFailed = true"
                    />

                    <span
                        x-show="imageFailed"
                        x-cloak
                        class="text-gray-400 dark:text-gray-500"
                    >
                        <x-filament::icon
                            :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy"
                            class="size-12"
                        />
                    </span>
                </button>

                <dialog
                    x-ref="awardImageDialog"
                    x-on:click="if ($event.target === $el) $el.close()"
                    class="m-auto max-h-[90vh] max-w-[90vw] overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-2xl outline-none dark:border-white/10 dark:bg-gray-900"
                >
                    <div class="relative">
                        <button
                            type="button"
                            x-on:click="$refs.awardImageDialog.close()"
                            class="absolute end-2 top-2 z-10 flex size-10 items-center justify-center rounded-full border-0 bg-gray-950/70 text-white shadow-lg"
                            aria-label="{{ __('Close') }}"
                        >
                            <x-filament::icon
                                :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                                class="size-5"
                            />
                        </button>

                        <img
                            src="{{ $awardImageUrl }}"
                            alt="{{ $getLabel() }}"
                            class="max-h-[85vh] max-w-[85vw] object-contain"
                        />
                    </div>
                </dialog>
            @else
                <div class="flex size-20 items-center justify-center text-gray-400 dark:text-gray-500">
                    <x-filament::icon
                        :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy"
                        class="size-12"
                    />
                </div>
            @endif
        </div>
    </div>
</div>
