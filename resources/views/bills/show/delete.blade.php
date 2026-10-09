<div class="row">
    <div class="col-md-8 col-md-push-2">
        <form action="{{ route('bill.destroy', $bill) }}" method="post" class="alert alert-danger alert-block" x-data="{ confirmed: false }">
            @csrf
            @method('DELETE')
            <h4>{{ __('Delete Bill') }}</h4>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="confirm" value="confirm" x-model="confirmed">
                    {{ __('Yes, please delete this bill!') }}
                </label>
            </div>
            <div class="alert alert-danger" x-show="confirmed" x-cloak>
                <h4 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> {{ __('Warning') }}</h4>
                {{ __('You are about to delete this bill.') }}
            </div>
            <button type="submit" class="btn btn-danger" x-bind:disabled="! confirmed"><i class="fa fa-trash"></i> <strong>{{ __('Delete Bill') }}</strong></button>
        </form>
    </div>
</div>
