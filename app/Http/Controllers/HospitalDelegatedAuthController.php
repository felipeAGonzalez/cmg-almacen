<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\HospitalDelegatedAuthException;
use App\Models\User;
use App\Services\HospitalDelegatedAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HospitalDelegatedAuthController extends Controller
{
    public function consume(Request $request, HospitalDelegatedAuthService $service): RedirectResponse
    {
        try {
            $token = $request->query('token');
            if (! is_string($token) || $token === '') {
                throw new HospitalDelegatedAuthException('missing_token');
            }

            $context = $service->consume($token);
            $user = User::query()->where('hospital_user_id', $context->hospitalUserId)->first();

            if (! $user) {
                Log::notice('Hospital delegated login rejected because no local account is linked.', [
                    'hospital_user_id_hash' => hash('sha256', $context->hospitalUserId),
                ]);

                return $this->safeRedirect($request, 'Tu cuenta de Hospitalización todavía no está vinculada con una cuenta de Almacén.');
            }

            if ($user->role !== UserRole::NURSE) {
                throw new HospitalDelegatedAuthException('local_role_not_nurse');
            }

            if ($request->user() && ! $request->user()->is($user)) {
                return $this->safeRedirect($request, 'Cierra la sesión actual antes de acceder con otra cuenta de Hospitalización.');
            }

            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $request->session()->put('hospital_context', $context->sessionData());

            Log::info('Hospital delegated login completed.', [
                'user_id' => $user->getKey(),
                'hospitalization_id_hash' => hash('sha256', $context->hospitalizationId),
            ]);

            return redirect()->route('nursing.hospital-context')
                ->withHeaders(['Referrer-Policy' => 'no-referrer']);
        } catch (HospitalDelegatedAuthException $exception) {
            Log::notice('Hospital delegated login rejected.', ['reason' => $exception->reason]);

            return $this->safeRedirect($request, $exception->safeMessage);
        }
    }

    public function context(Request $request): View
    {
        return view('nursing.hospital-context', [
            'hospitalContext' => $request->session()->get('hospital_context'),
        ]);
    }

    private function safeRedirect(Request $request, string $message): RedirectResponse
    {
        $route = $request->user() ? 'home' : 'login';

        return redirect()->route($route)
            ->with('error', $message)
            ->withHeaders(['Referrer-Policy' => 'no-referrer']);
    }
}
