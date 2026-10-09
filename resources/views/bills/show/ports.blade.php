{{-- Billed ports panel, with a remove button on the edit page --}}
<x-panel :title="__('Billed Ports')">
    <x-slot:table>
        <div class="list-group tw:mb-0">
            @forelse ($ports as $port)
                <div class="list-group-item">
                    @if ($removable ?? false)
                        <form action="{{ route('bill.port.detach', [$bill, $port]) }}" method="post" class="pull-right" onsubmit="return confirm('{{ __('Are you sure you wish to remove this port?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-minus"></i> {{ __('Remove Interface') }}</button>
                        </form>
                    @endif
                    <x-port-link :port="$port" /> {{ __('on') }} <x-device-link :device="$port->device" />
                </div>
            @empty
                <div class="list-group-item text-muted">{{ __('There are no ports assigned to this bill') }}</div>
            @endforelse
        </div>
    </x-slot:table>
</x-panel>
