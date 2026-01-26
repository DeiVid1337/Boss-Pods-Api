<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{

    public function authorize(): bool
    {
        return Gate::allows('create', User::class);
    }


    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,manager,seller'],
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
                Rule::requiredIf(function () {
                    $role = $this->input('role');
                    return in_array($role, ['manager', 'seller'], true);
                }),
            ],
            'is_active' => ['boolean'],
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email is already registered.',
            'password.required' => 'The password field is required.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'role.required' => 'The role field is required.',
            'role.string' => 'The role must be a string.',
            'role.in' => 'The role must be one of: admin, manager, seller.',
            'store_id.integer' => 'The store ID must be an integer.',
            'store_id.exists' => 'The selected store does not exist.',
            'store_id.required' => 'Store is required for manager and seller roles.',
            'is_active.boolean' => 'The is_active field must be a boolean.',
        ];
    }
}
