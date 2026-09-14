<div class="flex flex-nowrap items-center gap-2 overflow-hidden w-full">
    <img
        src="{{ asset('images/logo.svg') }}"
        alt="Logo"
        class="h-8 w-auto shrink-0"
    />
    <div class="flex flex-col text-left overflow-hidden">
        <span class="text-sm font-bold leading-tight text-gray-950 dark:text-white truncate" title="{{ config('app.name') }}">
            {{ config('app.name') }}
        </span>
        <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
            {{ __('з 24.02.2022') }}
        </span>
    </div>
</div>
