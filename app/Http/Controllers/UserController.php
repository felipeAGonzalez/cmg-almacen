<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with('warehouses')
            ->where('role', '!=', UserRole::ROOT->value)
            ->orderBy('name')
            ->orderBy('last_name_one')
            ->orderBy('last_name_two')
            ->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => UserRole::selectableCases(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $warehouseIds = $validated['warehouse_ids'] ?? [];
        unset($validated['warehouse_ids']);

        DB::transaction(function () use ($validated, $warehouseIds): void {
            $user = User::create($validated);

            if ($user->role !== UserRole::ADMINISTRATOR) {
                $user->warehouses()->sync($warehouseIds);
            }
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        $this->ensureManageable($user);

        return view('users.edit', [
            'user' => $user->load('warehouses'),
            'roles' => UserRole::selectableCases(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $validated = $request->validated();
        $warehouseIds = $validated['warehouse_ids'] ?? [];
        unset($validated['warehouse_ids']);

        if (! $request->filled('password')) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated, $warehouseIds): void {
            $user->update($validated);
            $user->warehouses()->sync(
                $user->role === UserRole::ADMINISTRATOR ? [] : $warehouseIds,
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        if ($user->is(auth()->user())) {
            return redirect()
                ->route('users.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    private function ensureManageable(User $user): void
    {
        abort_if($user->role === UserRole::ROOT, 403);
    }
}
