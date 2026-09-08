<?php

namespace App\Models;

use App\Models\Concerns\StampsOwnership;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class DashboardWidget extends Model
{
    use HasFactory;
    use StampsOwnership;

    protected $fillable = [
        'dashboard_id',
        'company_id',
        'user_id',
        'project_id',
        'chat_history_id',
        'master_widget_id',
        'master_filter_column',
        'title',
        'type',
        'query',
        'settings',
        'grid_position',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'grid_position' => 'array',
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owned', function (Builder $builder): void {
            /** @var User|null $user */
            $user = auth()->user();

            // Nessun utente autenticato: nessun risultato.
            if ($user === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            // Superadmin: vede tutti i record, nessun filtro.
            if ($user->isSuperAdmin()) {
                return;
            }

            // Admin azienda e utente normale: solo i record della propria
            // company (o senza company).
            $builder->where(function (Builder $query) use ($user): void {
                $query->whereNull('company_id')
                    ->orWhere('company_id', $user->company_id);
            });

            // Utente normale: in più, solo i propri record (o senza proprietario).
            if (! $user->isAdmin()) {
                $builder->where(function (Builder $query) use ($user): void {
                    $query->whereNull('user_id')
                        ->orWhere('user_id', $user->getKey());
                });
            }
        });
    }

    /**
     * Trasforma una stringa SQL raggruppata in una query di dettaglio (drill-down).
     *
     * @param  string  $sql  Stringa SQL originale
     * @param  array  $filters  Filtri chiave-valore per la riga selezionata (es. ['U.center' => 'Roma'])
     */
    public function convertSqlStringToDrillDown(string $sql, array $filters = []): string
    {
        $sql = rtrim(trim($sql), ';');

        // 1. Rimuove le clausole GROUP BY, HAVING e ORDER BY (senza GROUP BY,
        //    una HAVING residua sarebbe SQL non valida).
        $sql = preg_replace('/\s+GROUP\s+BY\s+[\s\S]+?(?=\s+HAVING|\s+ORDER\s+BY|\s+LIMIT|$)/i', '', $sql);
        $sql = preg_replace('/\s+HAVING\s+[\s\S]+?(?=\s+ORDER\s+BY|\s+LIMIT|$)/i', '', $sql);
        $sql = preg_replace('/\s+ORDER\s+BY\s+[\s\S]+?(?=\s+LIMIT|$)/i', '', $sql);

        // 2. SELECT di dettaglio: le voci aggregate vengono "spacchettate" nel
        //    campo interno; COUNT(*)/COUNT(1) non hanno un campo di dettaglio,
        //    quindi si ripiega su `*` per mostrare i record completi.
        if (preg_match('/^\s*SELECT\s+(?:DISTINCT\s+)?(.*?)\s+FROM\s+/is', $sql, $matches)) {
            $aggregatePattern = '/\b(COUNT|SUM|MAX|MIN|AVG|GROUP_CONCAT|STDDEV|STDDEV_POP|STDDEV_SAMP|VAR_POP|VAR_SAMP|VARIANCE)\s*\(/i';
            $items = [];
            $hadAggregate = false;
            $expandedAggregate = false;

            foreach ($this->splitTopLevel($matches[1]) as $item) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                if (preg_match($aggregatePattern, $item)) {
                    $hadAggregate = true;

                    $inner = trim((string) preg_replace_callback(
                        '/\b(?:COUNT|SUM|MAX|MIN|AVG|GROUP_CONCAT|STDDEV|STDDEV_POP|STDDEV_SAMP|VAR_POP|VAR_SAMP|VARIANCE)\s*\(\s*(?:DISTINCT\s+)?(.*?)\s*\)(?:\s+AS\s+[\w`"\']+)?/is',
                        fn (array $m): string => trim($m[1]),
                        $item,
                    ));

                    if ($inner === '' || $inner === '*' || is_numeric($inner)) {
                        continue;
                    }

                    $expandedAggregate = true;
                    $items[] = $inner;

                    continue;
                }

                $items[] = $item;
            }

            $projection = ($hadAggregate && ! $expandedAggregate) || $items === []
                ? '*'
                : implode(', ', $items);

            $sql = preg_replace('/^\s*SELECT\s+(?:DISTINCT\s+)?.*?\s+FROM\s+/is', "SELECT {$projection} FROM ", $sql);
        }

        // 3. Aggiunta dei filtri WHERE per il drill-down
        if (! empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $column => $value) {
                if ($value === null) {
                    $whereConditions[] = "{$column} IS NULL";

                    continue;
                }

                $escapedValue = is_numeric($value) ? $value : "'".addslashes((string) $value)."'";
                $whereConditions[] = "{$column} = {$escapedValue}";
            }

            $whereClause = implode(' AND ', $whereConditions);

            if (preg_match('/\bWHERE\b/i', $sql)) {
                $sql .= ' AND '.$whereClause;
            } else {
                $sql .= ' WHERE '.$whereClause;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $sql));
    }

    /**
     * Trasforma una query raggruppata in una query di dettaglio
     * rimuovendo aggregazioni e unpackando i campi aggregate.
     */
    public function convertToDrillDown(
        QueryBuilder|EloquentBuilder $groupedQuery,
        array $filters = []
    ): QueryBuilder|EloquentBuilder {
        $detailQuery = clone $groupedQuery;
        $base = $detailQuery instanceof EloquentBuilder ? $detailQuery->getQuery() : $detailQuery;

        // Reset clausole di raggruppamento e ordinamento
        $base->groups = null;
        $base->havings = null;
        $base->orders = null;

        if (! empty($base->columns)) {
            $grammar = $base->getGrammar() ?? $base->getConnection()->getQueryGrammar();

            $base->columns = array_map(function ($col) use ($grammar) {
                $sql = $col instanceof Expression ? (string) $col->getValue($grammar) : (string) $col;

                // Rimuove la funzione aggregata (COUNT, SUM, MAX, MIN, AVG) tenendo solo il campo interno
                if (preg_match('/\b(COUNT|SUM|MAX|MIN|AVG)\s*\(/i', $sql)) {
                    $unpackedSql = preg_replace_callback(
                        '/\b(COUNT|SUM|MAX|MIN|AVG)\s*\(\s*(?:DISTINCT\s+)?(.*?)\s*\)(\s+AS\s+[\w`"]+)?/i',
                        fn ($matches) => $matches[2],
                        $sql
                    );

                    return DB::raw(trim($unpackedSql));
                }

                return $col;
            }, $base->columns);
        } else {
            $base->columns = ['*'];
        }

        return $detailQuery->where($filters);
    }

    /**
     * Mappa `alias (minuscolo) => espressione SQL sorgente` per le voci NON
     * aggregate della SELECT di una query raggruppata: sono le dimensioni del
     * GROUP BY, usate come predicati nel drill-down di una riga.
     *
     * Sono riconosciute solo `<espr> AS <alias>` e gli identificatori semplici
     * (`col`, `t.col`, con o senza backtick); le espressioni complesse senza
     * `AS` esplicito vengono ignorate (drill-down più ampio ma SQL valida).
     * Ritorna vuoto se la query non è una SELECT singola e raggruppata.
     *
     * @return array<string, string>
     */
    public function selectDimensionExpressions(string $sql): array
    {
        $sql = trim($sql);

        if ($sql === ''
            || ! preg_match('/^\s*SELECT\b/i', $sql)
            || ! preg_match('/\bGROUP\s+BY\b/i', $sql)
            || preg_match_all('/\bSELECT\b/i', $sql) !== 1
            || ! preg_match('/^\s*SELECT\s+(?:DISTINCT\s+)?(.*?)\s+FROM\s+/is', $sql, $selectMatch)) {
            return [];
        }

        $expressions = [];

        foreach ($this->splitTopLevel($selectMatch[1]) as $item) {
            $item = trim($item);

            if ($item === '' || $item === '*'
                || preg_match('/\b(COUNT|SUM|MAX|MIN|AVG|GROUP_CONCAT|STDDEV|STDDEV_POP|STDDEV_SAMP|VAR_POP|VAR_SAMP|VARIANCE)\s*\(/i', $item)) {
                continue;
            }

            if (preg_match('/^(.+?)\s+AS\s+[`"\']?([A-Za-z0-9_]+)[`"\']?$/is', $item, $aliasMatch)) {
                $expressions[strtolower($aliasMatch[2])] = trim($aliasMatch[1]);

                continue;
            }

            if (preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?(?:\.`?([A-Za-z_][A-Za-z0-9_]*)`?)?$/', $item, $identMatch)) {
                $alias = $identMatch[2] ?? $identMatch[1];
                $expressions[strtolower($alias)] = $item;
            }
        }

        return $expressions;
    }

    /**
     * Divide un elenco separato da virgole rispettando le parentesi annidate
     * (es. la SELECT list o la GROUP BY list).
     *
     * @return list<string>
     */
    protected function splitTopLevel(string $list): array
    {
        $items = [];
        $buffer = '';
        $depth = 0;

        foreach (str_split($list) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }

            if ($char === ',' && $depth === 0) {
                $items[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $items[] = $buffer;
        }

        return $items;
    }

    /*

    // Stringa SQL di partenza
    $sql = "SELECT U.center,
              concat(U.last_name,' ',  U.first_name) as Specialista,
              COUNT(P.id) AS total_patients
            FROM patient_visits AS P
            JOIN users AS U
              ON P.created_by = U.id
            GROUP BY
              U.center,
              U.last_name,
              U.first_name
            ORDER BY U.center, U.last_name";

    // Filtri estratti dalla riga selezionata dall'utente
    $filters = [
        'U.center' => 'Centro Roma',
        'U.last_name' => 'Rossi'
    ];

    // Generazione della query di dettaglio
    $drillDownSql = convertSqlStringToDrillDown($sql, $filters);

    // Esecuzione via Laravel
    $results = DB::select($drillDownSql);
    */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Studio i cui filtri di coorte vengono applicati alla query del widget.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chatHistory(): BelongsTo
    {
        return $this->belongsTo(ChatHistory::class);
    }

    /**
     * Widget master di cui questo widget è un dettaglio/drill-down.
     */
    public function masterWidget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'master_widget_id');
    }

    /**
     * Widget di dettaglio che puntano a questo widget come master.
     */
    public function detailWidgets(): HasMany
    {
        return $this->hasMany(self::class, 'master_widget_id');
    }

    /**
     * Link pubblici di condivisione di questa tabella.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(DashboardWidgetShare::class);
    }
}
