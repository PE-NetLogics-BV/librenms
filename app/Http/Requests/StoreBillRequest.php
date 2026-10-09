<?php

namespace App\Http\Requests;

use App\Models\Bill;

class StoreBillRequest extends UpdateBillRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Bill::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'port_id' => ['nullable', 'integer', 'exists:ports,port_id'],
        ];
    }
}
