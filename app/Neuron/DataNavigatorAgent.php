<?php

declare(strict_types=1);

namespace App\Neuron;

use App\Models\ChatMessage;
use App\Models\SchemaLegend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\MySQL\MySQLSchemaTool;
use NeuronAI\Tools\Toolkits\MySQL\MySQLSelectTool;
use PDO;
use RuntimeException;

/**
 * Assistente che traduce domande in linguaggio naturale dei ricercatori in
 * query SQL di sola lettura sul database di coorte HIV (connessione `dbai`).
 * Sola lettura: esposti solo gli strumenti di schema e SELECT.
 */
class DataNavigatorAgent extends Agent
{
    protected ?string $threadId = null;

    protected PDO $pdo;

    protected PDO $pdodbai;

    public function __construct()
    {
        parent::__construct();

        $this->pdo = DB::connection()->getPdo();
        $this->pdodbai = DB::connection('dbai')->getPdo();
    }

    public function setThreadId(string $threadId): self
    {
        $this->threadId = $threadId;

        return $this;
    }

    protected function provider(): AIProviderInterface
    {
        $key = env('ANTHROPIC_API_KEY') ?: env('ANTHROPIC_KEY');

        if (blank($key)) {
            throw new RuntimeException('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env');
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            max_tokens: 8192,
        );
    }

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                <<<'TXT'
Sei un assistente che aiuta ricercatori medici a interrogare un database di coorte
HIV (dati clinici, immunologici, cardiovascolari raccolti a scopo di ricerca
epidemiologica/osservazionale). Non è un contesto di supporto a decisioni cliniche
su singoli pazienti: le query servono per analisi statistiche, aggregate e di
popolazione. Rispondi sempre nella stessa lingua della domanda (di norma italiano).
TXT,
                $this->schemaSection(),
            ],
            steps: [
                'Individua tabelle e colonne pertinenti usando SOLO lo schema fornito o gli strumenti di ispezione.',
                'Se un termine (farmaco, parametro, esito) non corrisponde a nessuna colonna nota, chiedi chiarimento invece di indovinare.',
                'Costruisci una query SELECT; per i campi con valori di lookup usa esattamente i valori elencati.',
                'Esegui la query con lo strumento di SELECT e riassumi il risultato in modo chiaro (tabella o elenco).',
            ],
            output: [
                'Genera ESCLUSIVAMENTE istruzioni SELECT. Mai INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, GRANT o istruzioni multiple separate da `;`.',
                'Usa solo tabelle e colonne realmente esistenti; non inventare mai nomi di colonna.',
                'Applica `LIMIT 1000` alle query che ritornano record singoli se l\'utente non specifica un limite. Nessun limite sulle query aggregate.',
                'Non includere colonne potenzialmente identificative (`iniziali`, `created_by`, `modified_by`) se non richieste esplicitamente per nome; per identificare i pazienti usa `pazientecode`, non l\'`id`.',
                'Filtra sempre `WHERE active = 1` a meno che la domanda richieda anche i record eliminati logicamente.',
                'I campi `*_TSA` sono storicizzati in `patient_visits` e come snapshot in `patients`: per analisi longitudinali usa SEMPRE `patient_visits`.',
                '`patients.datahiv` ha default `\'2000-01-01\'` per dato non noto: se filtri o calcoli su questo campo aggiungi `AND datahiv != \'2000-01-01\'` e segnalalo.',
                'Molte colonne data sono `varchar` (`D_HIV`, `INIZIO_ARV`, `D_AIDS`, `D_DIABETE`, `D_DECESSO`, `D_CARDIO1`, `D_CARDIO2`, `data_TSA`): segnala che il formato va verificato prima di usarle in un\'analisi. Le vere colonne DATE sono contrassegnate nello schema.',
                '`patient_visits.HIVRNA` è `tinyint`: trattalo come codifica/categoria, non come valore quantitativo di viral load; per la soppressione virologica preferisci `HIVRNAnorilevabile`.',
                'Mostra sempre la query SQL usata prima del risultato.',
                'Se il risultato ha limiti che possono inficiarne la validità statistica (dati mancanti, default, campione ridotto), evidenziali.',
            ],
        );
    }

    /**
     * Sezione di schema costruita dalla legenda (SchemaLegend) per patients e
     * patient_visits: nomi reali dei campi, commento, categoria data e valori
     * di lookup ammessi.
     */
    protected function schemaSection(): string
    {
        $legends = SchemaLegend::query()
            ->with('columns')
            ->whereIn('table_name', ['patients', 'patient_visits'])
            ->orderBy('order')
            ->get();

        if ($legends->isEmpty()) {
            return 'SCHEMA: legenda non ancora sincronizzata. Usa gli strumenti di ispezione del '
                .'database (schema, tabelle, colonne) prima di scrivere qualsiasi query.';
        }

        $out = ['# SCHEMA (connessione dbai)'];

        foreach ($legends as $legend) {
            $out[] = '';
            $out[] = "## {$legend->table_name}".($legend->description ? " — {$legend->description}" : '');

            foreach ($legend->columns as $column) {
                $line = "- `{$column->name}` ({$column->data_type})";

                if (! $column->nullable) {
                    $line .= ' NOT NULL';
                }

                if ($column->comment) {
                    $line .= ' — '.Str::limit(preg_replace('/\s+/', ' ', $column->comment), 140, '…');
                }

                if ($column->date_category !== null) {
                    $line .= ' [DATE reale]';
                }

                if (is_array($column->lookup_values) && $column->lookup_values !== []) {
                    $values = collect($column->lookup_values)
                        ->map(fn (array $value): string => (string) ($value['value'] ?? ''))
                        ->map(fn (string $value): string => $value === '' ? "''" : $value)
                        ->take(40)
                        ->implode(', ');
                    $line .= " — valori ammessi: {$values}";
                } elseif ($column->lookup_table !== null) {
                    $line .= " — riferimento a `{$column->lookup_table}`";
                }

                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /**
     * @return array<int, ToolInterface>
     */
    protected function tools(): array
    {
        return [
            MySQLSchemaTool::make($this->pdodbai),
            MySQLSelectTool::make($this->pdodbai),
        ];
    }

    protected function chatHistory(): ChatHistoryInterface
    {
        if ($this->threadId === null) {
            throw new RuntimeException('Thread ID non impostato: chiama setThreadId() prima di usare l\'agente.');
        }

        return new EloquentChatHistory(
            threadId: $this->threadId,
            modelClass: ChatMessage::class,
            contextWindow: 150000,
        );
    }
}
