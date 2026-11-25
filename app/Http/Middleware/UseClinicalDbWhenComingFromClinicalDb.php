<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se l'utente è entrato tramite il bridge SSO partendo da ClinicalDB
 * (BpmBridgeController salva il flag in sessione), la connessione `dbai`
 * e il profilo dell'assistente dati puntano a clinicaldb invece che al
 * database di default configurato in .env.
 */
class UseClinicalDbWhenComingFromClinicalDb
{
    public const SESSION_KEY = 'bridge_source_clinicaldb';

    public function handle(Request $request, Closure $next): Response
    {
        if (session()->get(self::SESSION_KEY) === true) {
            config([
                'database.connections.dbai.database' => 'clinicaldb',
                'data_navigator.profile' => 'hiv',
            ]);
        }

        return $next($request);
    }
}
