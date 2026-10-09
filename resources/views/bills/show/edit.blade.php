<div class="row">
    <div class="col-lg-6 col-md-12">
        <x-panel :title="__('Bill Properties')">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="tw:mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form id="edit-bill-form" method="post" action="{{ route('bill.update', $bill) }}" class="form-horizontal" role="form">
                @csrf
                @method('PUT')
                @include('bills.form', ['bill' => $bill, 'port' => null])
                <div class="form-group">
                    <div class="col-sm-offset-4 col-sm-8">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> {{ __('Save Properties') }}</button>
                    </div>
                </div>
            </form>
        </x-panel>
    </div>
    <div class="col-lg-6 col-md-12">
        @include('bills.show.ports', ['removable' => true])

        <x-panel :title="__('Add Port')">
            <form action="{{ route('bill.port.attach', $bill) }}" method="post" class="form-horizontal" role="form">
                @csrf
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="device">{{ __('Device') }}</label>
                    <div class="col-sm-8">
                        <select class="form-control input-sm" id="device" name="device" onchange="billDeviceChanged()"></select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="port_id">{{ __('Port') }}</label>
                    <div class="col-sm-8">
                        <select class="form-control input-sm" id="port_id" name="port_id"></select>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-8">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('Add Port') }}</button>
                    </div>
                </div>
            </form>
        </x-panel>
    </div>
</div>

@push('scripts')
<script>
    init_select2('#device', 'device', {}, null, '{{ __('Select Device') }}');
    init_select2('#port_id', 'port', function (params) { params.device = $('#device').val(); return params; }, null, '{{ __('Select Port') }}');
    function billDeviceChanged() {
        $('#port_id').val(null).trigger('change');
    }
</script>
@endpush
