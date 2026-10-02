<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\HospitalIntegrationException;
use App\Models\User;
use App\Services\HospitalNurseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HospitalNurseOptionsController extends Controller
{
    public function __invoke(Request $request, HospitalNurseService $service): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = isset($validated['user_id'])
            ? User::query()->findOrFail($validated['user_id'])
            : null;

        abort_if($user?->role === UserRole::ROOT, 403);

        try {
            $options = $service->optionsFor($user);
        } catch (HospitalIntegrationException $exception) {
            Log::warning('Hospital nurse options could not be loaded.', [
                'user_id' => $user?->getKey(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'No fue posible consultar las enfermeras de Hospitalización.',
            ], 503);
        }

        return response()->json([
            'data' => $options['available']->map(fn ($nurse): array => [
                'hospital_user_id' => $nurse->hospitalUserId,
                'name' => $nurse->name,
                'email' => $nurse->email,
                'is_current' => $user?->hospital_user_id === $nurse->hospitalUserId,
            ])->values(),
            'meta' => [
                'total' => $options['total'],
                'available' => $options['available']->count(),
            ],
        ]);
    }
}
