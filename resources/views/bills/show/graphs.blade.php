<h3>{{ $summary['title'] }}</h3>
<strong>{{ __('Billing Period from :from to :to', $period) }}</strong>
<br /><br />

<div class="row">
    <div class="col-lg-6 col-lg-push-6">
        @include('bills.show.ports')
    </div>
    <div class="col-lg-6 col-lg-pull-6">
        <x-panel :title="__('Bill Summary')">
            <x-slot:table>
                <table class="table tw:mb-0">
                    <tr>
                        <td>{{ $summary['used'] }} {{ __('of') }} {{ $summary['allowed'] }} ({{ $summary['percent'] }}%) {{ $summary['detail'] }}</td>
                        <td style="width: 210px;">{!! $summary['bar'] !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2">{{ __('Predicted usage') }}: {{ $summary['predicted'] }}</td>
                    </tr>
                </table>
            </x-slot:table>
        </x-panel>
    </div>
</div>

@foreach ($graphs as $title => $args)
    <x-panel :title="$title">
        <x-slot:table>
            {!! \LibreNMS\Util\Url::graphTag($args) !!}
        </x-slot:table>
    </x-panel>
@endforeach
