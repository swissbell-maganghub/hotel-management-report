<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', 'in:gm,hod,staff,it_admin'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_read_only' => ['sometimes', 'boolean'],
            'department_permissions' => ['sometimes', 'array'],
            'department_permissions.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'department_permissions.*.access_level' => ['required', 'in:read,input,review'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $role = $this->input('role');
            if (in_array($role, [User::ROLE_HOD, User::ROLE_STAFF], true) && empty($this->input('department_id'))) {
                $validator->errors()->add('department_id', 'Role HOD dan Staff wajib memiliki Department Scope.');
            }
        });
    }
}
