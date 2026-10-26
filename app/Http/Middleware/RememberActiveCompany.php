<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ricorda in sessione l'azienda selezionata nel pannello, così dopo il login
 * (o aprendo /admin) si torna sull'ultima scelta.
 */
class RememberActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            session(['active_company_id' => $tenant->getKey()]);
        }

        return $next($request);
    }
}
