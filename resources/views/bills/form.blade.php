{{-- Bill properties, shared by the create dialog and the edit page. $bill is null when creating. --}}
@php
    $base = (int) \App\Facades\LibrenmsConfig::get('billing.base', 1000);
    $type = old('bill_type', $bill->bill_type ?? 'cdr');
    $dir = old('dir_95th', $bill->dir_95th ?? (\App\Facades\LibrenmsConfig::get('billing.95th_default_agg') ? 'agg' : 'in'));

    // show a stored limit in the largest unit that keeps it >= 1
    $split = function (?int $value, array $units, int $default) use ($base): array {
        for ($i = count($units) - 1; $i > 0; $i--) {
            if ($value && $value >= $base ** $i) {
                return ['value' => $value / $base ** $i, 'unit' => $units[$i]];
            }
        }

        return ['value' => $value ?: null, 'unit' => $units[$default]];
    };
    $quota = $split($bill->bill_quota ?? null, ['B', 'MB', 'GB', 'TB'], 2);
    $cdr = $split($bill->bill_cdr ?? null, ['bps', 'Kbps', 'Mbps', 'Gbps'], 2);
@endphp

<h4>{{ __('Bill Information') }}</h4>
<div class="form-group">
    <label for="bill_name" class="col-sm-4 control-label">{{ __('Description') }}</label>
    <div class="col-sm-8">
        <input class="form-control input-sm" type="text" id="bill_name" name="bill_name" value="{{ old('bill_name', $bill->bill_name ?? $port?->port_descr_descr) }}" required>
    </div>
</div>
<div class="form-group">
    <label class="col-sm-4 control-label">{{ __('Billing Type') }}</label>
    <div class="col-sm-8">
        <label class="radio-inline">
            <input type="radio" name="bill_type" value="cdr" @checked($type === 'cdr') onchange="billType()"> {{ __('CDR 95th') }}
        </label>
        <label class="radio-inline">
            <input type="radio" name="bill_type" value="quota" @checked($type === 'quota') onchange="billType()"> {{ __('Quota') }}
        </label>
    </div>
</div>
<div id="cdrDiv">
    <div class="form-group">
        <label class="col-sm-4 control-label" for="bill_cdr">{{ __('CDR') }}</label>
        <div class="col-sm-3">
            <input class="form-control input-sm" type="text" id="bill_cdr" name="bill_cdr" value="{{ old('bill_cdr', $cdr['value']) }}">
        </div>
        <div class="col-sm-5">
            <select name="bill_cdr_type" class="form-control input-sm">
                @foreach (['Kbps' => __('Kilobits per second (Kbps)'), 'Mbps' => __('Megabits per second (Mbps)'), 'Gbps' => __('Gigabits per second (Gbps)')] as $unit => $label)
                    <option value="{{ $unit }}" @selected(old('bill_cdr_type', $cdr['unit']) === $unit)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-4 control-label">{{ __('95th Calculation') }}</label>
        <div class="col-sm-8">
            <label class="radio-inline">
                <input type="radio" name="dir_95th" value="in" @checked($dir !== 'agg')> {{ __('Max In/Out') }}
            </label>
            <label class="radio-inline">
                <input type="radio" name="dir_95th" value="agg" @checked($dir === 'agg')> {{ __('Aggregate') }}
            </label>
        </div>
    </div>
</div>
<div id="quotaDiv">
    <div class="form-group">
        <label class="col-sm-4 control-label" for="bill_quota">{{ __('Quota') }}</label>
        <div class="col-sm-3">
            <input class="form-control input-sm" type="text" id="bill_quota" name="bill_quota" value="{{ old('bill_quota', $quota['value']) }}">
        </div>
        <div class="col-sm-5">
            <select name="bill_quota_type" class="form-control input-sm">
                @foreach (['MB' => __('Megabytes (MB)'), 'GB' => __('Gigabytes (GB)'), 'TB' => __('Terabytes (TB)')] as $unit => $label)
                    <option value="{{ $unit }}" @selected(old('bill_quota_type', $quota['unit']) === $unit)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="form-group">
    <label class="col-sm-4 control-label" for="bill_day">{{ __('Billing Day') }}</label>
    <div class="col-sm-2">
        <select name="bill_day" id="bill_day" class="form-control input-sm">
            @for ($day = 1; $day <= 31; $day++)
                <option value="{{ $day }}" @selected((int) old('bill_day', $bill->bill_day ?? 1) === $day)>{{ $day }}</option>
            @endfor
        </select>
    </div>
</div>
<fieldset>
    <h4>{{ __('Optional Information') }}</h4>
    <div class="form-group">
        <label class="col-sm-4 control-label" for="bill_custid">{{ __('Customer Reference') }}</label>
        <div class="col-sm-8">
            <input class="form-control input-sm" type="text" id="bill_custid" name="bill_custid" value="{{ old('bill_custid', $bill->bill_custid ?? '') }}">
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-4 control-label" for="bill_ref">{{ __('Billing Reference') }}</label>
        <div class="col-sm-8">
            <input class="form-control input-sm" type="text" id="bill_ref" name="bill_ref" value="{{ old('bill_ref', $bill->bill_ref ?? $port?->port_descr_circuit) }}">
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-4 control-label" for="bill_notes">{{ __('Notes') }}</label>
        <div class="col-sm-8">
            <textarea class="form-control input-sm" id="bill_notes" name="bill_notes">{{ old('bill_notes', $bill->bill_notes ?? $port?->port_descr_speed) }}</textarea>
        </div>
    </div>
</fieldset>

@push('scripts')
<script>
    function billType() {
        const selected = $('input[name=bill_type]:checked').val();
        $('#cdrDiv').toggle(selected === 'cdr');
        $('#quotaDiv').toggle(selected === 'quota');
    }
    billType();
</script>
@endpush
