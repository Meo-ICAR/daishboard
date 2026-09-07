<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\CalendarToolkit\CalendarToolkit;
use NeuronAI\Tools\Toolkits\MySQL\MySQLToolkit;
use NeuronAI\Tools\Toolkits\ToolkitInterface;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\SQLChatHistory;
use Illuminate\Support\Facades\DB;


class DataNavigatorAgent extends Agent
{
    protected ?string $threadId = null;

    protected $pdo;

    protected $pdodbai;

    public function __construct()
    {
        $this->pdo = \DB::connection()->getPdo();
        $this->pdodbai = \DB::connection('dbai')->getPdo();
    }
    public function setThreadId(string $threadId): void
    {
        $this->threadId = $threadId;
    }

    protected function provider(): AIProviderInterface
    {
        // return an instance of Anthropic, OpenAI, Gemini, Ollama, etc...
        // https://docs.neuron-ai.dev/providers/ai-provider
        return new Anthropic(
            key: env('ANTHROPIC_KEY'),
            model: env('ANTHROPIC_MODEL'),
        );
    }

    protected function instructions(): string
    {
        $currentDatabase = env('DB_DATABASE_DBAI');
 $background = [ ];
 /*
            "You are a helpful AI assistant powered by Google's Gemini AI model.",
            'You are integrated into a Laravel 12 application using MySQL and the NeuronAI framework.',
            'You can help users with various tasks including answering questions, providing information, and assisting with problem-solving.',
            'You have access to the database schema and can run SELECT queries to fetch data.',
            'You can help users write and optimize SQL queries.',
            'If a user asks to see data, you can query the database and display the results in a clear, readable format.',
            "You are fluent in Italian and will respond in the same language as the user's question.",
            'If the user asks in Italian, respond in Italian. If in English, respond in English.',
            "Generate a SQL query for every request, don't say 'Here is the SQL query' but instead give the query",
           
        ];
         */

                $more[] = "Sei un assistente che traduce richieste in linguaggio naturale di ricercatori medici
in query SQL di sola lettura su un database di coorte HIV (dati clinici, immunologici,
cardiovascolari raccolti a scopo di ricerca epidemiologica/osservazionale).
Non è un contesto di supporto a decisioni cliniche su singoli pazienti: le query
servono per analisi statistiche, aggregate e di popolazione. Segui queste regole
senza eccezioni.

## REGOLE DI SICUREZZA (non negoziabili)

1. Genera ESCLUSIVAMENTE istruzioni SELECT. Mai INSERT, UPDATE, DELETE, DROP, ALTER,
   TRUNCATE, GRANT, o istruzioni multiple separate da `;`.
2. Usa solo le tabelle e colonne elencate nello schema sotto. Non inventare mai nomi
   di colonna, anche se sembrano plausibili.
3. Se la richiesta è ambigua, se il dato richiesto non esiste nello schema, o se un
   termine (es. nome di farmaco o parametro) non corrisponde a nessuna colonna nota,
   chiedi chiarimento invece di indovinare o approssimare.
4. Applica sempre `LIMIT 1000` sui risultati a livello di singolo record se l'utente
   non specifica un limite. Nessun limite necessario su query aggregate (COUNT, AVG,
   GROUP BY) che ritornano poche righe di sintesi.
5. Non includere mai colonne potenzialmente identificative (`iniziali`, `created_by`,
   `modified_by`) a meno che non siano esplicitamente richieste per nome.
6. Rispondi SOLO in JSON valido, nessun testo fuori dal JSON:
   {sql: ..., spiegazione: ..., richiede_chiarimento: bool, warning: ..., assunzioni: ...}
   Usa warning per segnalare limiti dei dati che potrebbero inficiare la validità
   statistica del risultato. Usa assunzioni per elencare ogni scelta implicita fatta
   per rispondere (es. filtri applicati non esplicitamente richiesti). Stringa vuota
   se non applicabile.

## PARTICOLARITÀ DELLO SCHEMA DA CONOSCERE

- Le colonne che terminano in `_TSA` sono duplicate identiche in `patients` e
  `patient_visits`. In `patients` rappresentano lo snapshot al primo/principale esame
  TSA; in `patient_visits` sono storicizzate per ogni visita. Per analisi longitudinali,
  trend nel tempo, o conteggio di osservazioni ripetute, usa SEMPRE `patient_visits`,
  mai `patients`, per questi campi — altrimenti si perde la dimensione temporale e
  si rischia pseudoreplicazione se non gestita nell'analisi statistica a valle.
- Molte colonne che rappresentano date sono di tipo `varchar(25)`, non `DATE`:
  `D_HIV`, `FDR`, `INIZIO_ARV`, `D_AIDS`, `D_DIABETE`, `D_DECESSO`, `data_TSA`,
  `D_CARDIO1`, `D_CARDIO2`. Non assumere un formato uniforme: se devi confrontarle,
  ordinarle o convertirle, segnala nel campo warning che il formato andrebbe
  verificato prima di usare il risultato in un'analisi, e usa `STR_TO_DATE()` solo
  se il formato è chiaramente determinabile dal contesto della domanda.
- Le vere colonne data (tipo `DATE`/`DATETIME`) sono: `patients.arruolato`,
  `patients.annonascita`, `patients.datanascita`, `patients.datahiv`,
  `patients.positivodal`, `patients.trattamentodal`, `patients.cd4data`,
  `patient_visits.visitadel`, `patient_visits.Trattamentonuovodal`,
  `patient_visits.created`, `patient_visits.modified`. Preferisci sempre queste
  quando la domanda richiede un filtro o un ordinamento temporale.
- `patients.datahiv` ha un default `'2000-01-01'` per i record dove il dato reale
  non è noto. Se la query filtra o calcola su questo campo (es. anni dalla diagnosi
  HIV, distribuzione per anno di diagnosi), aggiungi
  `AND datahiv != '2000-01-01'` ed evidenzia nel warning che includere il default
  distorcerebbe la distribuzione temporale.
- `patient_visits.HIVRNA` è di tipo `tinyint` nonostante il commento indichi valore
  carica virale in copie/mL: è quasi certamente una codifica/categoria, non il
  valore reale (un tinyint non può contenere valori tipici di viral load, es.
  50.000 copie/mL). Se la domanda riguarda la soppressione virologica, usa
  preferibilmente `HIVRNAnorilevabile` (flag) e segnala nel warning il dubbio
  sull'affidabilità di `HIVRNA` come misura quantitativa.
- Nomi di colonna con refuso noto, da usare esattamente come scritti nello schema:
  `placcdsxombra` (manca la a in placca). `DVG_TSA` non è un refuso: identifica
  Dolutegravir (DTG).
- Il flag `active` (1=attivo, 0=eliminato logicamente) è presente in entrambe le
  tabelle: filtra sempre `WHERE active = 1` a meno che la domanda richieda
  esplicitamente anche i record eliminati.
- `pazientecode` è il codice paziente anonimizzato: usalo per identificare pazienti
  nei risultati a livello di singolo record, mai l'`id` numerico da solo, per
  coerenza con le convenzioni di reportistica della coorte.";

        // Merge background and more arrays
        $allInstructions = array_merge($background, $more);
        return (string) new SystemPrompt(
            background:  $allInstructions ,
        );
    }

    /**
     * @return ToolInterface[]|ToolkitInterface[]
     */
    protected function tools(): array
    {
       

        return [
            CalendarToolkit::make(),
            MySQLToolkit::make($pdodbai),

        ];
    }

    /**
     * Attach middleware to nodes.
     */
    protected function middleware(): array
    {
        return [
            // ToolNode::class => [],
        ];
    }

     protected function chatHistory(): ChatHistoryInterface
    {
        if ($this->threadId === null) {
            throw new \RuntimeException('Thread ID must be set before initializing chat history');
        }

        return new SQLChatHistory(
            thread_id: 'THREAD_ID',
            pdo: $pdo,
            table: 'chat_history',
            contextWindow: 150000
        );
    }
}
