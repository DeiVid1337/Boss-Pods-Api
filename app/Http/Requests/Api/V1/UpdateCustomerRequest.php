<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('customer'));
    }


    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($customer->id),
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 20 characters.',
            'phone.unique' => 'The phone number is already registered.',
        ];
    }
}
