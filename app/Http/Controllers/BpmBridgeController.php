<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BpmBridgeController extends Controller
{
    /**
     * Landing di single sign-on proveniente dal BPM esterno.
     *
     * - il token non viene mai registrato nei log in chiaro;
     * - la chiamata di verifica ha un timeout esplicito e richiede HTTPS;
     * - la sessione viene rigenerata dopo il login (anti session fixation);
     * - in caso di errore si risponde con un 403 generico senza dettagli interni.
     */
    public function handle(Request $request, string $subjectId): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'user_email' => ['required', 'email', 'max:255'],
        ]);

        $token = $validated['token'];
        $userEmail = $validated['user_email'];

        $bpmBaseUrl = (string) config('services.bpm.url');

        if (! str_starts_with($bpmBaseUrl, 'https://')) {
            Log::warning('BPM bridge: URL di verifica non HTTPS, richiesta rifiutata.');
            abort(403);
        }

        try {
            $response = Http::asJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->post("{$bpmBaseUrl}/api/verify-token", [
                    'token' => $token,
                    'email' => $userEmail,
                ]);
        } catch (\Throwable $e) {
            Log::warning('BPM bridge: verifica token fallita per errore di rete.', [
                'email' => $userEmail,
            ]);
            abort(403);
        }

        if ($response->failed() || $response->json('valid') !== true) {
            Log::warning('BPM bridge: accesso rifiutato.', [
                'email' => $userEmail,
                'subject_id' => $subjectId,
            ]);
            abort(403, 'Token BPM non valido o scaduto.');
        }

        $user = User::query()->where('email', $userEmail)->first();

        if (! $user || ! $user->canAccessPanel(filament()->getPanel('admin'))) {
            Log::warning('BPM bridge: accesso rifiutato (utente non trovato o non autorizzato).', [
                'email' => $userEmail,
                'subject_id' => $subjectId,
            ]);
            abort(403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('BPM bridge: accesso riuscito.', [
            'email' => $userEmail,
            'subject_id' => $subjectId,
        ]);

        return redirect('/admin')->with('message', 'Accesso effettuato tramite BPM');
    }
}
