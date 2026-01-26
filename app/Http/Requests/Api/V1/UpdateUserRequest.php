<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('user'));
    }


    public function rules(): array
    {
        $user = $this->route('user');
        $role = $this->input('role', $user->role);
        $roleIsBeingChanged = $this->has('role') && $this->input('role') !== $user->role;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'in:admin,manager,seller'],
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
                Rule::requiredIf(function () use ($role, $user, $roleIsBeingChanged) {
                    if (!in_array($role, ['manager', 'seller'], true)) {
                        return false;
                    }

                    if ($roleIsBeingChanged) {
                        return true;
                    }

                    return $user->store_id === null;
                }),
            ],
            'is_active' => ['boolean'],
        ];
    }


    public function messages(): array
    {
        return [
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email is already registered.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters.',
            'role.string' => 'The role must be a string.',
            'role.in' => 'The role must be one of: admin, manager, seller.',
            'store_id.integer' => 'The store ID must be an integer.',
            'store_id.exists' => 'The selected store does not exist.',
            'store_id.required' => 'Store is required for manager and seller roles.',
            'is_active.boolean' => 'The is_active field must be a boolean.',
        ];
    }
}
