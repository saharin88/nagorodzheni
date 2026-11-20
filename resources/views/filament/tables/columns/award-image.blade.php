@php
    $award = $getRecord();
    $imagePath = $getState();

    $imageUrl = filled($imagePath)
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($imagePath)
        : null;
@endphp

<div x-data="{ imageFailed: false }" class="flex w-full justify-center">
    @if (filled($imageUrl))
        <button
            type="button"
            class="flex h-12 w-12 shrink-0 cursor-zoom-in items-center justify-center border-0 bg-transparent p-0"
            aria-label="{{ __('View image: :name', ['name' => $award->name]) }}"
            x-on:click="$refs.awardImageDialog.showModal()"
        >
            <img
                x-show="! imageFailed"
                src="{{ $imageUrl }}"
                alt="{{ $award->name }}"
                loading="lazy"
                class="h-12 w-12 shrink-0 object-contain"
                x-on:error="imageFailed = true"
            />

            <span
                x-show="imageFailed"
                x-cloak
                class="text-gray-400 dark:text-gray-500"
            >
                <x-filament::icon
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy"
                    class="size-8"
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
                    src="{{ $imageUrl }}"
                    alt="{{ $award->name }}"
                    class="max-h-[85vh] max-w-[85vw] object-contain"
                />
            </div>
        </dialog>
    @else
        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-gray-400 dark:text-gray-500">
            <x-filament::icon
                :icon="\Filament\Support\Icons\Heroicon::OutlinedTrophy"
                class="size-8"
            />
        </div>
    @endif
</div>

