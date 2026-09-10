<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DashboardWidgets\DashboardWidgetResource;
use App\Models\ChatMessage;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Neuron\DataNavigatorAgent;
use App\Support\CompanyScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;
use UnitEnum;

/**
 * Chat con l'assistente dati (NeuronAI): traduce domande in query SQL di sola
 * lettura sul database di coorte e ne mostra i risultati.
 */
class DataAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Legenda';

    protected static ?string $navigationLabel = 'Assistente AI';

    protected static ?string $title = 'Assistente AI';

    protected string $view = 'filament.pages.data-assistant';

    /** Thread corrente (query string, così la conversazione è condivisibile). */
    public ?string $thread = null;

    /** @var array<int, array{role: string, html: string, sql: list<string>, question: ?string}> */
    public array $messages = [];

    public string $prompt = '';

    public ?string $error = null;

    public function mount(): void
    {
        $prefix = $this->threadPrefix();

        if (! is_string($this->thread) || ! str_starts_with($this->thread, $prefix)) {
            $this->thread = session('data_assistant_thread');
        }

        if (! is_string($this->thread) || ! str_starts_with($this->thread, $prefix)) {
            $this->thread = $prefix.Str::ulid()->toBase32();
        }

        session(['data_assistant_thread' => $this->thread]);
        $this->loadMessages();
    }

    protected function threadPrefix(): string
    {
        return 'u'.(auth()->id() ?? 'guest').'-';
    }

    protected function loadMessages(): void
    {
        $rows = CompanyScope::byOwner(ChatMessage::query())
            ->where('thread_id', $this->thread)
            ->orderBy('id')
            ->get();

        $messages = [];
        $lastQuestion = null;

        foreach ($rows as $row) {
            $text = $this->renderContent($row->content);

            if ($text === '') {
                continue;
            }

            if ($row->role === 'user') {
                $lastQuestion = $text;
                $messages[] = ['role' => 'user', 'html' => nl2br(e($text)), 'sql' => [], 'question' => null];

                continue;
            }

            $messages[] = [
                'role' => $row->role,
                'html' => $this->markdown($text),
                'sql' => $row->role === 'assistant' ? $this->extractSql($text) : [],
                'question' => $lastQuestion,
            ];
        }

        $this->messages = $messages;
    }

    public function send(): void
    {
        $this->error = null;
        $prompt = trim($this->prompt);

        if ($prompt === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'html' => nl2br(e($prompt)), 'sql' => [], 'question' => null];
        $this->prompt = '';

        try {
            $reply = (new DataNavigatorAgent)
                ->setThreadId($this->thread)
                ->chat(new UserMessage($prompt))
                ->getMessage();

            $text = (string) $reply->getContent();

            $this->messages[] = [
                'role' => 'assistant',
                'html' => $this->markdown($text),
                'sql' => $this->extractSql($text),
                'question' => $prompt,
            ];
        } catch (Throwable $e) {
            Log::error('DataAssistant chat failed', ['thread' => $this->thread, 'exception' => $e]);
            $this->error = 'Errore nell\'elaborazione della richiesta: '.$e->getMessage();
        }
    }

    public function newConversation(): void
    {
        $this->thread = $this->threadPrefix().Str::ulid()->toBase32();
        session(['data_assistant_thread' => $this->thread]);
        $this->messages = [];
        $this->prompt = '';
        $this->error = null;
    }

    public function openThread(string $thread): void
    {
        $ownsThread = ChatMessage::query()
            ->where('thread_id', $thread)
            ->where('user_id', auth()->id())
            ->exists();

        if ($ownsThread || str_starts_with($thread, $this->threadPrefix())) {
            $this->thread = $thread;
            session(['data_assistant_thread' => $thread]);
            $this->error = null;
            $this->loadMessages();
        }
    }

    /**
     * Conversazioni dell'utente autenticato: un thread_id distinto per riga,
     * ordinati per data dell'ultimo messaggio (MAX(created_at)).
     *
     * @return array<int, array{thread_id: string, label: string, last_at: ?Carbon, messages: int, active: bool}>
     */
    public function recentThreads(): array
    {
        $userId = auth()->id();

        $threads = ChatMessage::query()
            ->where('user_id', $userId)
            ->selectRaw('thread_id, MAX(created_at) as last_at, COUNT(*) as messages')
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->limit(30)
            ->get();

        if ($threads->isEmpty()) {
            return [];
        }

        $labels = ChatMessage::query()
            ->where('user_id', $userId)
            ->where('role', 'user')
            ->whereIn('thread_id', $threads->pluck('thread_id'))
            ->orderBy('id')
            ->get(['thread_id', 'content'])
            ->groupBy('thread_id')
            ->map(fn ($group): string => $this->renderContent($group->first()->content));

        return $threads->map(fn ($thread): array => [
            'thread_id' => $thread->thread_id,
            'label' => Str::limit(strip_tags($labels[$thread->thread_id] ?? '') ?: 'Conversazione', 48),
            'last_at' => $thread->last_at ? Carbon::parse($thread->last_at) : null,
            'messages' => (int) $thread->messages,
            'active' => $thread->thread_id === $this->thread,
        ])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newConversation')
                ->label('Nuova conversazione')
                ->icon('heroicon-o-plus')
                ->action('newConversation'),
        ];
    }

    /**
     * Azione: salva una query mostrata dall'assistente come DashboardWidget,
     * usando la domanda dell'utente come titolo. Ricevuti `messageIndex` e
     * `sqlIndex` come argomenti dal pulsante sotto il messaggio.
     */
    public function createWidgetAction(): Action
    {
        return Action::make('createWidget')
            ->label('Crea widget dalla query')
            ->modalHeading('Crea widget dashboard')
            ->modalSubmitActionLabel('Crea widget')
            ->fillForm(function (array $arguments): array {
                $message = $this->messages[$arguments['messageIndex'] ?? -1] ?? [];
                $queries = $message['sql'] ?? [];

                return [
                    'title' => $message['question'] ?? '',
                    'query' => $queries[$arguments['sqlIndex'] ?? 0] ?? '',
                    'dashboard_id' => Dashboard::query()->orderBy('order')->orderBy('id')->value('id'),
                    'type' => 'Table',
                ];
            })
            ->schema([
                TextInput::make('title')
                    ->label('Titolo (domanda)')
                    ->required()
                    ->maxLength(255),
                Select::make('dashboard_id')
                    ->label('Dashboard')
                    ->options(fn (): array => Dashboard::query()
                        ->orderBy('order')->orderBy('id')
                        ->pluck('title', 'id')->all())
                    ->required(),
                TextInput::make('type')
                    ->label('Tipo')
                    ->default('Table'),
                Textarea::make('query')
                    ->label('Query SQL')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $widget = DashboardWidget::create([
                    'dashboard_id' => $data['dashboard_id'],
                    'title' => $data['title'],
                    'type' => filled($data['type'] ?? null) ? $data['type'] : 'Table',
                    'query' => $data['query'],
                    'order' => 0,
                    'is_active' => true,
                ]);

                Notification::make()
                    ->title('Widget creato')
                    ->body($widget->title)
                    ->success()
                    ->actions([
                        Action::make('open')
                            ->label('Apri widget')
                            ->url(DashboardWidgetResource::getUrl('view', ['record' => $widget]), shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /**
     * Estrae i blocchi SELECT/WITH dal testo Markdown della risposta.
     *
     * @return list<string>
     */
    protected function extractSql(string $text): array
    {
        $blocks = [];

        if (preg_match_all('/```(?:sql)?\s*\r?\n(.*?)```/is', $text, $matches)) {
            $blocks = $matches[1];
        }

        if ($blocks === [] && preg_match_all('/(?:^|\n)\s*((?:WITH|SELECT)\b[\s\S]+?;)/i', $text, $matches)) {
            $blocks = $matches[1];
        }

        return collect($blocks)
            ->map(fn (string $block): string => trim($block))
            ->filter(fn (string $block): bool => preg_match('/^\s*(WITH|SELECT)\b/i', $block) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Estrae il testo dai content-block NeuronAI, ignorando tool_use / tool_result.
     */
    protected function renderContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (! is_array($content)) {
            return '';
        }

        $text = collect($content)
            ->map(function ($block): string {
                if (is_string($block)) {
                    return $block;
                }

                if (is_array($block) && ($block['type'] ?? null) === 'text') {
                    return (string) ($block['content'] ?? $block['text'] ?? '');
                }

                return '';
            })
            ->filter()
            ->implode("\n");

        return trim($text);
    }

    protected function markdown(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        return Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
