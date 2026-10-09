<div class="row">
    <div class="col-md-8 col-md-push-2">
        <form action="{{ route('bill.reset', $bill) }}" method="post" class="alert alert-danger alert-block" x-data="{ confirmed: false }">
            @csrf
            <h4>{{ __('Reset Bill') }}</h4>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="confirm" value="mysql" x-model="confirmed">
                    {{ __('Yes, please reset the collected data of all interfaces on this bill!') }}
                </label>
            </div>
            <div class="alert alert-danger" x-show="confirmed" x-cloak>
                <h4 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> {{ __('Warning') }}</h4>
                {{ __('Are you sure you want to reset all collected data and history of this bill?') }}
            </div>
            <button type="submit" class="btn btn-danger" x-bind:disabled="! confirmed"><i class="fa fa-refresh"></i> <strong>{{ __('Reset Bill') }}</strong></button>
        </form>
    </div>
</div>
