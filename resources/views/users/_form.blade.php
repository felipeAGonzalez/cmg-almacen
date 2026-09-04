@php
    $isEditing = isset($user);
    $selectedRole = old('role', $isEditing ? $user->role->value : '');
    $selectedWarehouseIds = collect(old('warehouse_ids', $isEditing ? $user->warehouses->modelKeys() : []))
        ->map(fn ($warehouseId) => (int) $warehouseId)
        ->all();
@endphp

<form method="POST" action="{{ $isEditing ? route('users.update', $user) : route('users.store') }}" novalidate>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="form-section">
        <h3 class="form-section-title mb-1">Información personal</h3>
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="name" class="form-label">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" maxlength="255" autocomplete="given-name" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="last_name_one" class="form-label">Primer apellido <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="last_name_one" name="last_name_one" value="{{ old('last_name_one', $user->last_name_one ?? '') }}" class="form-control @error('last_name_one') is-invalid @enderror" maxlength="255" autocomplete="family-name" required>
                @error('last_name_one')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="last_name_two" class="form-label">Segundo apellido <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input type="text" id="last_name_two" name="last_name_two" value="{{ old('last_name_two', $user->last_name_two ?? '') }}" class="form-control @error('last_name_two') is-invalid @enderror" maxlength="255" autocomplete="additional-name">
                @error('last_name_two')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="email" class="form-label">Correo electrónico <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title mb-1">Cargo y accesos</h3>
        <p class="small text-body-secondary mb-4">Define el cargo y los almacenes a los que tendrá acceso.</p>

        <div class="row g-3">
            <div class="col-lg-5">
                <label for="role" class="form-label">Cargo <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                    <option value="">Selecciona un cargo</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-lg-7">
                <div id="administratorAccessNotice" class="alert alert-info mb-0" hidden>
                    <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>El Administrador tiene acceso a todos los almacenes.
                </div>

                <div id="warehouseSelection">
                    <fieldset>
                        <legend class="form-label fs-6 mb-1">Almacenes asignados <span class="text-danger" aria-hidden="true">*</span></legend>
                        <p id="warehouseSelectionHelp" class="small text-body-secondary mb-2">Selecciona uno o varios almacenes.</p>

                        @if ($warehouses->isEmpty())
                            <div class="alert alert-warning mb-0">No hay almacenes disponibles para asignar.</div>
                        @else
                            <div class="warehouse-options @if ($errors->has('warehouse_ids') || $errors->has('warehouse_ids.*')) border-danger @endif">
                                @foreach ($warehouses as $warehouse)
                                    <div class="form-check warehouse-option">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="warehouse_ids[]"
                                            value="{{ $warehouse->id }}"
                                            id="warehouse_{{ $warehouse->id }}"
                                            @checked(in_array($warehouse->id, $selectedWarehouseIds, true))
                                        >
                                        <label class="form-check-label w-100" for="warehouse_{{ $warehouse->id }}">{{ $warehouse->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('warehouse_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @foreach ($errors->get('warehouse_ids.*') as $warehouseErrors)
                            @foreach ($warehouseErrors as $warehouseError)
                                <div class="text-danger small mt-2">{{ $warehouseError }}</div>
                            @endforeach
                        @endforeach
                    </fieldset>
                </div>
            </div>

            <div class="col-12" id="hospitalUserLink" hidden>
                <label for="hospital_user_id" class="form-label">ID de usuario en Hospitalización</label>
                <input type="text" id="hospital_user_id" name="hospital_user_id" value="{{ old('hospital_user_id', $user->hospital_user_id ?? '') }}" class="form-control @error('hospital_user_id') is-invalid @enderror" maxlength="255" autocomplete="off">
                <div class="form-text">Identificador utilizado para vincular esta cuenta con el sistema de Hospitalización.</div>
                @error('hospital_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <h3 class="form-section-title mb-1">Seguridad</h3>
        @if ($isEditing)
            <p class="small text-body-secondary mb-4">Déjala en blanco para conservar la contraseña actual.</p>
        @else
            <p class="small text-body-secondary mb-4">La contraseña debe tener al menos 8 caracteres.</p>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="password" class="form-label">Contraseña @unless ($isEditing)<span class="text-danger" aria-hidden="true">*</span>@endunless</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(! $isEditing)>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">Confirmar contraseña @unless ($isEditing)<span class="text-danger" aria-hidden="true">*</span>@endunless</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" @required(! $isEditing)>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('users.index') }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar usuario' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('role');
            const warehouseSelection = document.getElementById('warehouseSelection');
            const administratorNotice = document.getElementById('administratorAccessNotice');
            const warehouseInputs = warehouseSelection.querySelectorAll('input[type="checkbox"]');
            const hospitalUserLink = document.getElementById('hospitalUserLink');
            const hospitalUserInput = document.getElementById('hospital_user_id');
            const warehouseSelectionHelp = document.getElementById('warehouseSelectionHelp');

            function enforceNurseWarehouseLimit(changedInput) {
                if (roleSelect.value !== 'nurse' || !changedInput.checked) {
                    return;
                }

                warehouseInputs.forEach(function (input) {
                    if (input !== changedInput) {
                        input.checked = false;
                    }
                });
            }

            function updateWarehouseSelection() {
                const isAdministrator = roleSelect.value === 'administrator';
                administratorNotice.hidden = !isAdministrator;
                warehouseSelection.hidden = isAdministrator;
                warehouseInputs.forEach(function (input) {
                    input.disabled = isAdministrator;
                });
                const isNurse = roleSelect.value === 'nurse';
                warehouseSelectionHelp.textContent = isNurse
                    ? 'Selecciona como máximo un almacén.'
                    : 'Selecciona uno o varios almacenes.';
                hospitalUserLink.hidden = !isNurse;
                hospitalUserInput.disabled = !isNurse;
            }

            roleSelect.addEventListener('change', updateWarehouseSelection);
            warehouseInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    enforceNurseWarehouseLimit(input);
                });
            });
            updateWarehouseSelection();
        });
    </script>
@endpush
