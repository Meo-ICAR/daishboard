<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ArchiprevaleatUsersSeeder extends Seeder
{
    /**
     * Importa gli utenti del database legacy clinicaldb (coorte HIV) come
     * utenti della company Archiprevaleat. Solo nome ed email sono noti da
     * quella fonte: se un utente con la stessa email esiste già viene
     * lasciato invariato (nessun insert, nessun update).
     *
     * @var list<array{0: string, 1: string}>
     */
    private const USERS = [
        ['Alessio Pampaloni', 'alepampa89@gmail.com'],
        ['Alessandra Guida', 'alessandra.guidamontepao@gmail.com'],
        ['Alessandra Tartaglia', 'alessandratartaglia@yahoo.it'],
        ['ALESSANDRO RAIMONDI', 'alessandro.raimondi@ospedaleniguarda.it'],
        ['Alessia Del Villano', 'alessiadelvillano@outlook.it'],
        ['ALICE RANZANI', 'alice.ranzani@asst-monza.it'],
        ['Angelo Salomonemegna', 'angelo.salomonemegna@aornsanpio.it'],
        ['Annamaria De Luca', 'annan.deluca10@gmail.com'],
        ['Antonella Foschi', 'anto.fos@libero.it'],
        ['Antonella Gallicchio', 'antonella.gallicchio@ospedalideicolli.it'],
        ['Rosa Basile', 'basilerosa@virgilio.it'],
        ['Giovanni Bellagamba', 'bellagambagiovanni@gmail.com'],
        ['Benedetto M. Celesia', 'bmcelesia@tin.it'],
        ['Diana Canetti', 'canetti.diana@hsr.it'],
        ['Amedeo Capetti', 'capetti.amedeo@asst-fbf.sacco.it'],
        ['carlo lanna', 'carlo.lanna@libero.it'],
        ['Antonella Castagna', 'castagna.antonella1@hsr.it'],
        ['Chiara Bellacosa', 'chiara.bellacosa@inwind.it'],
        ['GABIELLA CHIEFFO', 'chieffogabriella@gmail.com'],
        ['CLARA STECCA', 'clara.stecca@aulss8.veneto.it'],
        ['TOMMASO CLEMENTE', 'clemente.tommaso@hsr.it'],
        ['Annamaria Colella', 'colannamaria@alice.it'],
        ['Marco Esposito', 'comunicazione@klinksolution.it'],
        ['DANIELE TESORO', 'daniele.tesoro@asst-santipaolocarlo.it'],
        ['DARIO BERNACCHIA', 'dario.bernacchia@asst-ovestmi.it'],
        ['DAVIDE MOSCHESE', 'davide.moschese@gmail.com'],
        ['DAVIDE RICABONI', 'davide.ricaboni@asst-lariana.it'],
        ['Antimo Di Lorenzo', 'direzione@klinksolution.it'],
        ['Domenico Angiletta', 'domenico.angiletta@gmail.com'],
        ['Dora Masiello', 'Dora.80@live.it'],
        ['Antonio Lillo', 'dotlillo@tim.it'],
        ['Edoardo Paolo Drago', 'e.p.drago@gmail.com'],
        ['Elena Ricci', 'ed.ricci@libero.it'],
        ['Elena Angeli', 'elena.angeli@asst-fbf.sacco.it'],
        ['Elio Manzillo', 'elio.manzillo@ospedalideicolli.it'],
        ['Elisa Monge', 'elisa.monge87@gmail.com'],
        ['Elisa Suardi', 'elisa_suardi@yahoo.it'],
        ['Elisabetta Grilli', 'elisabetta.grilli@inmi.it'],
        ['EMANUELE DRAPPERO', 'emanuele.drappero@unito.it'],
        ['EMANUELE PALOMBA', 'emanuele.palomba@unimi.it'],
        ['Francesco Perilli', 'f_perilli@virgilio.it'],
        ['FABIO BORGONOVO', 'fabio.borgonovo@asst-fbf-sacco.it'],
        ['Fabrizio Pulvirenti', 'fabrizio.pulvirenti@tin.it'],
        ['Federico Maffeo', 'federicomaffeo@virgilio.it'],
        ['Anna Fineo', 'fineoanna@tiscali.it'],
        ['FRANCESCA ALLADIO', 'francesca.alladio@gmail.com'],
        ['Francesca Colucci', 'francescacolucci90@gmail.com'],
        ['Francesco Laguardia', 'francesco.laguardia93@gmail.com'],
        ['Francesco Pallotti', 'Francesco.pallotti@unikore.it'],
        ['FRANCESCO PETRI', 'francesco.petri@asst-fbf-sacco.it'],
        ['Laura Galli', 'galli.laura@hsr.it'],
        ['Giacinto Casciano', 'giacinto.casciano@gmail.com'],
        ['GIACOMO POZZA', 'giacomo.pozza@unimi.it'],
        ['Giovanni Di Caprio', 'giov.dicaprio@gmail.com'],
        ['Giovanni Di Filippo', 'giovanni.difilippo@unina.it'],
        ['GIULIA VIERO', 'giulia.viero@unimi.it'],
        ['Lorenzo Rindi', 'l.rindi@gmail.com'],
        ['Laura Milazzo', 'laura.milazzo@unimi.it'],
        ['Laura Occhiello', 'laura.occhiello@gmail.com'],
        ['Loredana Alessio', 'loredana.alessio@gmail.com'],
        ['Sabrina Mameli', 'm.sabrina.mameli@gmail.com'],
        ['Carmelo Mangano', 'mangano.carmelo@gmail.com'],
        ['Manuela Ceccarelli', 'manuela.ceccarelli@unikore.it'],
        ['Marcello Trizzino', 'marcello.trizzino@hotmail.com'],
        ['Maria Elena Locatelli', 'mariaelena_locatelli@yahoo.it'],
        ['MARTINA GERBI', 'martina.gerbi@asst-fbf-sacco.it'],
        ['Carlo Martinelli', 'martinellicanio8@gmail.com'],
        ['MARZIA GARAU', 'marzia.garau@gmail.com'],
        ['Michele Paterno', 'michelepat93@gmail.com'],
        ['MICOL FERRARA', 'micol.ferrara29@gmail.com'],
        ['Davide Mililli', 'mililli.dave@gmail.com'],
        ['Maria Rosaria Pellegrino', 'mrpellegrino@libero.it'],
        ['Nicola Squillace', 'nicolasquillace@gmail.com'],
        ['Paolo Maggi', 'p_maggi@yahoo.com'],
        ['Gianfranco Panico', 'Panico.gianfranco@gmail.com'],
        ['Simone Passerini', 'passerini.simone@gmail.com'],
        ['Pier giuseppe Meo', 'piergiuseppe.meo@gmail.com'],
        ['Andrea Poli', 'poli.andrea@hsr.it'],
        ['Rossella Fontana Del Vecchio', 'r.fontanadelvecchio@gmail.com'],
        ['Salvatore Martini', 'salvatoremartini76@gmail.com'],
        ['BATCH BATCH', 'sanraffaele@klink.it'],
        ['Daniele Scuderi', 'scuderi.dan@gmail.com'],
        ['Serena Rita Bruno', 'serenaritabruno@gmail.com'],
        ['Sergio Maria Ferrara', 'sferrara@ospedaliriunitifoggia.it'],
        ['Silvia Pecoraro', 'silviapecoraro90@gmail.com'],
        ['Silvia Pecoraro', 'silviapecoraro90@gmail.cox'],
        ['Simona Landonio', 'simona.landonio@gmail.com'],
        ['Sonia Sofia', 'sonia.sofia@email.it'],
        ['Stefania Cicalini', 'stefania.cicalini@inmi.it'],
        ['Giovanna Sanna', 'studi.clinici.inf@gmail.com'],
        ['Francesco Taccari', 'taccari@hotmail.it'],
        ['Maria Teresa Russo', 'terry93.mtr@gmail.com'],
        ['VALENTINA MORENA', 'v.morena@asst-lecco.it'],
        ['Valentina Fortunato', 'valefortunato@libero.it'],
        ['Valentina Fortunato', 'valefortunato@libero.itx'],
        ['Valentina Iodice', 'valentinaiodicemd@gmail.com'],
        ['verdiana Zollo', 'verdianazollo@gmail.com'],
        ['Viviana Rizzo', 'viviana.rizzo@outlook.it'],
        ['Sabrina Zocco', 'zoccosabrina@gmail.com'],
    ];

    public function run(): void
    {
        $company = Company::where('name', 'Archiprevaleat')->firstOrFail();

        foreach (self::USERS as [$name, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'company_id' => $company->id,
                    'password' => Hash::make('demo1234'),
                ]
            );
        }
    }
}
