<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        $managedUser = $this->route('managedUser');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($managedUser?->id),
            ],
            'role' => ['required', 'in:gm,hod,staff,it_admin'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['required', 'boolean'],
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

            /** @var User|null $managedUser */
            $managedUser = $this->route('managedUser');
            if ($managedUser && $managedUser->id === $this->user()->id && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            }

            if ($managedUser && $managedUser->isItAdmin() && $managedUser->is_active) {
                $losingAdminAccess = ($role !== User::ROLE_IT_ADMIN) || ! $this->boolean('is_active');
                if ($losingAdminAccess) {
                    $otherActiveAdmins = User::where('role', User::ROLE_IT_ADMIN)
                        ->where('is_active', true)
                        ->where('id', '!=', $managedUser->id)
                        ->count();

                    if ($otherActiveAdmins === 0) {
                        $validator->errors()->add('role', 'Minimal harus ada 1 IT Administrator yang aktif di sistem.');
                    }
                }
            }
        });
    }
}
