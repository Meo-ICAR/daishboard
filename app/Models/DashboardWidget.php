<?php

namespace App\Models;

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
        static::creating(function (self $widget): void {
            if ($widget->user_id === null && auth()->check() && ! auth()->user()?->isAdmin()) {
                $widget->user_id = auth()->id();
            }

            if ($widget->company_id === null && auth()->check() && ! auth()->user()?->isSuperAdmin()) {
                $widget->company_id = auth()->user()->company_id;
            }
        });

        static::addGlobalScope('owned', function (Builder $builder): void {
            /** @var User|null $user */
            $user = auth()->user();

            // Nessun utente autenticato: nessun risultato
            if ($user === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            // Super-admin (is_admin = true e company_id nullo): vede tutto
            if ($user->isSuperAdmin()) {
                return;
            }

            // Admin di azienda: vede i widget della stessa azienda
            if (true || $user->isAdmin()) {
                $companyId = $user->company_id;
                $builder->where(function (Builder $query) use ($companyId): void {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                });

                return;
            }

            // Utente normale: vede solo i propri widget
            $userId = $user->id;
            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
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
        // 1. Rimuove le clausole GROUP BY e ORDER BY
        $sql = preg_replace('/\s+GROUP\s+BY\s+[\s\S]+?(?=\s+HAVING|\s+ORDER\s+BY|\s+LIMIT|$)/i', '', $sql);
        $sql = preg_replace('/\s+ORDER\s+BY\s+[\s\S]+?(?=\s+LIMIT|$)/i', '', $sql);

        // 2. Unpack delle funzioni aggregate nella SELECT (COUNT, SUM, MAX, MIN, AVG)
        if (preg_match('/^\s*SELECT\s+(.*?)\s+FROM\s+/is', $sql, $matches)) {
            $selectClause = $matches[1];

            $unpackedSelect = preg_replace_callback(
                '/\b(COUNT|SUM|MAX|MIN|AVG)\s*\(\s*(?:DISTINCT\s+)?(.*?)\s*\)(\s+AS\s+[\w`"]+)?/i',
                fn ($m) => $m[2],
                $selectClause
            );

            $sql = preg_replace('/^\s*SELECT\s+.*?\s+FROM\s+/is', "SELECT {$unpackedSelect} FROM ", $sql);
        }

        // 3. Aggiunta dei filtri WHERE per il drill-down
        if (! empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $column => $value) {
                $escapedValue = is_numeric($value) ? $value : "'".addslashes($value)."'";
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
