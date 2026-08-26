@extends('layouts.app')

@section('page-title', 'Usuarios')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Usuarios</h2>
            <p>Administra los usuarios y sus accesos al sistema.</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Nuevo usuario
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="user-list-title">
        <div class="admin-card-header d-flex align-items-center justify-content-between">
            <div>
                <h3 class="h6 fw-bold mb-1" id="user-list-title">Usuarios registrados</h3>
                <p class="small text-body-secondary mb-0">{{ $users->total() }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</p>
            </div>
        </div>

        @if ($users->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                <h3 class="h5">No hay usuarios registrados.</h3>
                <p class="text-body-secondary mb-4">Crea el primer usuario para comenzar a administrar sus accesos.</p>
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2" aria-hidden="true"></i>Crear usuario
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Correo electrónico</th>
                            <th scope="col">Cargo</th>
                            <th scope="col">Almacenes</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php
                                $fullName = collect([$user->name, $user->last_name_one, $user->last_name_two])->filter()->implode(' ');
                                $roleClass = match ($user->role) {
                                    \App\Enums\UserRole::ADMINISTRATOR => 'role-administrator',
                                    \App\Enums\UserRole::WAREHOUSE_MANAGER => 'role-warehouse-manager',
                                    \App\Enums\UserRole::NURSE => 'role-nurse',
                                    default => 'role-legacy',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="admin-user-avatar flex-shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                        <div>
                                            <strong class="d-block fw-semibold">{{ $fullName }}</strong>
                                            @if ($user->is(Auth::user()))
                                                <small class="text-body-secondary">Usuario actual</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="role-badge {{ $roleClass }}">{{ $user->role->label() }}</span>
                                    @if ($user->role === \App\Enums\UserRole::LEGACY_USER)
                                        <small class="d-block text-warning-emphasis mt-1">Requiere actualización</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($user->role === \App\Enums\UserRole::ADMINISTRATOR)
                                        <span class="text-primary fw-medium"><i class="bi bi-globe2 me-1" aria-hidden="true"></i>Todos los almacenes</span>
                                    @elseif ($user->warehouses->isNotEmpty())
                                        @foreach ($user->warehouses as $warehouse)
                                            <span class="warehouse-badge">{{ $warehouse->name }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-body-secondary">Sin asignar</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar a {{ $fullName }}">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </a>
                                    @unless ($user->is(Auth::user()))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUserModal"
                                            data-modal-name="{{ $fullName }}"
                                            data-modal-name-target="#deleteUserName"
                                            data-modal-delete-url="{{ route('users.destroy', $user) }}"
                                            data-modal-form-target="#deleteUserForm"
                                        >
                                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                        </button>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-top px-3 py-3">
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteUserModalLabel">¿Eliminar usuario?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará al usuario y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteUserName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteUserForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
