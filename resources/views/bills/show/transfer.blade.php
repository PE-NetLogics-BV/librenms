<h3>{{ __('Transfer Report') }}</h3>
<strong>{{ __('Billing Period from :from to :to', $period) }}</strong>
<br /><br />

<div class="row">
    <div class="col-lg-5 col-lg-push-7">
        @include('bills.show.ports')
    </div>
    <div class="col-lg-7 col-lg-pull-5">
        <x-panel :title="__('Bill Summary')">
            <x-slot:table>
                <table class="table table-striped tw:mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('Bandwidth') }}</th>
                        <th>{{ __('Used') }}</th>
                        <th>{{ __('Allowed') }}</th>
                        <th>{{ __('Average') }}</th>
                        <th>{{ __('Estimated') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($transfer as $row)
                        <tr>
                            <th>{{ $row['name'] }}</th>
                            <td>
                                @if ($row['overuse'] ?? false)
                                    <span style="color: #{{ $row['color'] }}; font-weight: bold;">{{ $row['data'] }}</span>
                                @else
                                    {{ $row['data'] }}
                                @endif
                            </td>
                            <td>{{ $row['allowed'] }}</td>
                            <td>{{ $row['average'] }}</td>
                            <td>{{ $row['estimated'] }}</td>
                            <td>{!! $row['bar'] !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </x-slot:table>
        </x-panel>
    </div>
</div>

@foreach ($graphs as $title => $args)
    <x-panel :title="$title">
        {!! \LibreNMS\Util\Url::graphTag($args) !!}
    </x-panel>
@endforeach
