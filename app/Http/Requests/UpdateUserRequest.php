<?php

namespace App\Http\Requests;

use App\Contracts\HospitalNurseProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Rules\HospitalNurseExists;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return ! $user instanceof User || $user->role !== UserRole::ROOT;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $requiresWarehouses = fn (): bool => $this->string('role')->value() === UserRole::WAREHOUSE_MANAGER->value;
        $isAdministrator = fn (): bool => $this->string('role')->value() === UserRole::ADMINISTRATOR->value;
        $isNurse = fn (): bool => $this->string('role')->value() === UserRole::NURSE->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name_one' => ['required', 'string', 'max:255'],
            'last_name_two' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in($this->selectableRoleValues())],
            'hospital_user_id' => [
                Rule::excludeIf(fn (): bool => $this->string('role')->value() !== UserRole::NURSE->value),
                'nullable',
                'string',
                'max:255',
                new HospitalNurseExists(
                    app(HospitalNurseProvider::class),
                    $user instanceof User && $user->hospital_user_id !== null
                        ? (string) $user->hospital_user_id
                        : null,
                ),
                Rule::unique('users', 'hospital_user_id')->ignore($user),
            ],
            'warehouse_ids' => [Rule::excludeIf($isAdministrator), Rule::requiredIf($requiresWarehouses), 'array', Rule::when($isNurse, ['max:1'])],
            'warehouse_ids.*' => [Rule::excludeIf($isAdministrator), 'integer', 'distinct', 'exists:warehouses,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'last_name_one.required' => 'El primer apellido es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'role.required' => 'El cargo es obligatorio.',
            'role.in' => 'El cargo seleccionado no es válido.',
            'hospital_user_id.unique' => 'La enfermera seleccionada ya está vinculada a otro usuario.',
            'warehouse_ids.required' => 'Debes seleccionar al menos un almacén para el cargo elegido.',
            'warehouse_ids.array' => 'Los almacenes seleccionados no son válidos.',
            'warehouse_ids.min' => 'Debes seleccionar al menos un almacén para el cargo elegido.',
            'warehouse_ids.max' => 'Una enfermera sólo puede estar asignada a un almacén.',
            'warehouse_ids.*.distinct' => 'No puedes seleccionar el mismo almacén más de una vez.',
            'warehouse_ids.*.exists' => 'Uno de los almacenes seleccionados no existe.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'last_name_one' => 'primer apellido',
            'last_name_two' => 'segundo apellido',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'role' => 'cargo',
            'hospital_user_id' => 'usuario de Hospitalización',
            'warehouse_ids' => 'almacenes',
            'warehouse_ids.*' => 'almacén',
        ];
    }

    /**
     * @return list<string>
     */
    private function selectableRoleValues(): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::selectableCases(),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'hospital_user_id' => filled($this->input('hospital_user_id'))
                ? trim((string) $this->input('hospital_user_id'))
                : null,
        ]);
    }
}
