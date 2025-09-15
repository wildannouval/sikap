<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    </flux:main>
</x-layouts.app.sidebar>
