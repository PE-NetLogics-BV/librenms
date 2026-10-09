<h3>{{ __('Historical Usage') }}</h3>

<x-panel :title="__('Monthly Usage')">
    <x-slot:table>
        <img class="graph-image" src="{{ $monthly_graph }}" style="margin: 15px 5px 25px 5px;" alt="{{ __('Monthly Usage') }}" />
    </x-slot:table>
</x-panel>

<table class="table table-striped">
    <thead>
    <tr style="font-weight: bold;">
        <th width="250">{{ __('Period') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Allowed') }}</th>
        <th>{{ __('Inbound') }}</th>
        <th>{{ __('Outbound') }}</th>
        <th>{{ __('Peak Out') }}</th>
        <th>{{ __('Peak In') }}</th>
        <th>{{ __('Total') }}</th>
        <th>{{ __('95th %ile') }}</th>
        <th style="text-align: center;">{{ __('Overusage') }}</th>
        <th colspan="2" style="text-align: right;">
            <a href="{{ $all_details_link }}"><i class="fa fa-bar-chart fa-lg icon-theme" aria-hidden="true" title="{{ __('Show details') }}"></i> {{ __('Show details') }}</a>
        </th>
    </tr>
    </thead>
    <tbody>
    @forelse ($history as $entry)
        <tr>
            <td><span style="font-weight: bold;" class="interface">{{ $entry['period'] }}</span></td>
            <td>{{ $entry['type'] }}</td>
            <td>{{ $entry['allowed'] }}</td>
            <td>{{ $entry['in'] }}</td>
            <td>{{ $entry['out'] }}</td>
            <td>{{ $entry['peak_out'] }}</td>
            <td>{{ $entry['peak_in'] }}</td>
            <td>@if ($entry['type'] === 'Quota')<b>{{ $entry['total'] }}</b>@else{{ $entry['total'] }}@endif</td>
            <td>@if ($entry['type'] === 'CDR')<b>{{ $entry['rate_95th'] }}</b>@else{{ $entry['rate_95th'] }}@endif</td>
            <td style="text-align: center;">
                @if ($entry['overuse'] === null)
                    -
                @else
                    <span style="color: #{{ $entry['color'] }}; font-weight: bold;">{{ $entry['overuse'] }}</span>
                @endif
            </td>
            <td width="250">{!! $entry['bar'] !!}</td>
            <td><a href="{{ $entry['detail_link'] }}"><i class="fa fa-bar-chart fa-lg icon-theme" aria-hidden="true" title="{{ __('Show details') }}"></i></a></td>
        </tr>
        @if ($entry['detail_graphs'])
            <tr>
                <td colspan="12">
                    @foreach ($entry['detail_graphs'] as $src)
                        <img class="graph-image" src="{{ $src }}" style="margin: 15px 5px 25px 5px;" alt="" /><br />
                    @endforeach
                </td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="12" class="text-muted">{{ __('No closed billing periods yet') }}</td>
        </tr>
    @endforelse
    </tbody>
</table>
