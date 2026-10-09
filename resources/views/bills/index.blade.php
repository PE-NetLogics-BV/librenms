@extends('layouts.librenmsv1')

@section('title', __('Traffic Bills'))

@section('content')
<div class="container-fluid" x-data="{ createModalOpen: @js($open_create || $errors->any()) }">
    <x-panel>
        <x-slot:heading class="tw:flex tw:justify-between tw:items-center">
            <h3 class="panel-title">{{ __('Traffic Bills') }}</h3>
            @can('create', App\Models\Bill::class)
                <button type="button" class="btn btn-default btn-sm" x-on:click="createModalOpen = true"><i class="fa fa-plus"></i> {{ __('Create Bill') }}</button>
            @endcan
        </x-slot:heading>
        <x-slot:table>
            <div class="table-responsive">
                <table id="bills" class="table table-hover table-condensed" data-url="{{ route('table.bills') }}">
                    <thead>
                    <tr>
                        <th data-column-id="bill_name" data-order="asc">{{ __('Billing name') }}</th>
                        <th data-column-id="notes" data-sortable="false"></th>
                        <th data-column-id="bill_type">{{ __('Type') }}</th>
                        <th data-column-id="bill_allowed" data-align="right">{{ __('Allowed') }}</th>
                        <th data-column-id="total_data_in" data-align="right">{{ __('Inbound') }}</th>
                        <th data-column-id="total_data_out" data-align="right">{{ __('Outbound') }}</th>
                        <th data-column-id="total_data" data-align="right">{{ __('Total') }}</th>
                        <th data-column-id="rate_95th" data-align="right">{{ __('95th Percentile') }}</th>
                        <th data-column-id="overusage" data-sortable="false" data-align="center">{{ __('Overusage') }}</th>
                        <th data-column-id="predicted" data-sortable="false" data-align="center">{{ __('Predicted') }}</th>
                        <th data-column-id="graph" data-sortable="false"></th>
                        <th data-column-id="actions" data-sortable="false"></th>
                    </tr>
                    </thead>
                </table>
            </div>
        </x-slot:table>
    </x-panel>

    @can('create', App\Models\Bill::class)
    <x-modal show="createModalOpen" title="{{ __('Add Traffic Bill') }}" maxWidth="xl">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="tw:mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="create-bill-form" method="post" action="{{ route('bill.store') }}" class="form-horizontal">
            @csrf
            <div class="form-group">
                <label class="col-sm-4 control-label" for="device">{{ __('Device') }}</label>
                <div class="col-sm-8">
                    <select class="form-control input-sm" id="device" name="device" onchange="billDeviceChanged()"></select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-4 control-label" for="port_id">{{ __('Port') }}</label>
                <div class="col-sm-8">
                    <select class="form-control input-sm" id="port_id" name="port_id"></select>
                </div>
            </div>
            @include('bills.form', ['bill' => null, 'port' => $port])
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-default" x-on:click="createModalOpen = false">{{ __('Cancel') }}</button>
            <button type="submit" form="create-bill-form" class="btn btn-primary"><i class="fa fa-check"></i> {{ __('Add Bill') }}</button>
        </x-slot:footer>
    </x-modal>
    @endcan
</div>
@endsection

@section('scripts')
<script>
    const billFilters = @js($filters);

    const grid = $('#bills').bootgrid({
        ajax: true,
        rowCount: [50, 100, 250, -1],
        columnSelection: false,
        templates: {
            header: "<div id=\"@{{ctx.id}}\" class=\"@{{css.header}}\"><div class=\"row\"><div class=\"col-sm-8\">" +
                "<span class=\"form-inline\" id=\"table-filters\"><fieldset class=\"form-group\">" +
                "<select name=\"period\" id=\"period\" class=\"form-control input-sm\"><option value=\"\">{{ __('Current Billing Period') }}</option><option value=\"prev\">{{ __('Previous Billing Period') }}</option></select> " +
                "<select name=\"bill_type\" id=\"bill_type\" class=\"form-control input-sm\"><option value=\"\">{{ __('All Types') }}</option><option value=\"cdr\">{{ __('CDR') }}</option><option value=\"quota\">{{ __('Quota') }}</option></select> " +
                "<select name=\"state\" id=\"state\" class=\"form-control input-sm\"><option value=\"\">{{ __('All States') }}</option><option value=\"under\">{{ __('Under Quota') }}</option><option value=\"over\">{{ __('Over Quota') }}</option></select>" +
                "</fieldset></span></div><div class=\"col-sm-4 actionBar\"><p class=\"@{{css.search}}\"></p><p class=\"@{{css.actions}}\"></p></div></div></div>"
        },
        post: function () {
            return {
                period: $('#period').val(),
                bill_type: $('#bill_type').val(),
                state: $('#state').val()
            };
        }
    }).on('initialized.rs.jquery.bootgrid', function () {
        $('#period').val(billFilters.period);
        $('#bill_type').val(billFilters.bill_type);
        $('#state').val(billFilters.state);
        $('#table-filters select').on('change', function () { grid.bootgrid('reload'); });
    });

    @can('create', App\Models\Bill::class)
    const selectConfig = {dropdownParent: $('#create-bill-form'), width: '100%'};
    init_select2('#device', 'device', {}, @js($port ? ['id' => $port->device_id, 'text' => $port->device->displayName()] : null), '{{ __('Select Device') }}', selectConfig);
    init_select2('#port_id', 'port', function (params) { params.device = $('#device').val(); return params; }, @js($port ? ['id' => $port->port_id, 'text' => $port->ifAlias ?: $port->ifName] : null), '{{ __('Select Port') }}', selectConfig);
    function billDeviceChanged() {
        $('#port_id').val(null).trigger('change');
    }
    @endcan
</script>
@endsection
