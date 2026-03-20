<x-layouts::app :title="__('Tournament')">
    <div class="w-full">
        <livewire:tournament-details :tournamentId="$tournament->id" />
    </div>
</x-layouts::app>

