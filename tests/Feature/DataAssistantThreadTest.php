<?php

namespace Tests\Feature;

use App\Filament\Pages\DataAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DataAssistantThreadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inserisce i messaggi di un thread nella forma salvata da NeuronAI:
     * blocchi content JSON con chiave "content" per il testo, intervallati da
     * turni vuoti "[]" (tool-use round-trip).
     *
     * @param  list<array{role: string, text: ?string}>  $turns
     */
    private function seedThread(string $threadId, int $userId, array $turns): void
    {
        $rows = [];

        foreach ($turns as $i => $turn) {
            $content = $turn['text'] === null
                ? '[]'
                : json_encode([['meta' => [], 'type' => 'text', 'content' => $turn['text']]]);

            $rows[] = [
                'thread_id' => $threadId,
                'role' => $turn['role'],
                'content' => $content,
                'meta' => null,
                'user_id' => $userId,
                'company_id' => null,
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ];
        }

        DB::table('chat_messages')->insert($rows);
    }

    public function test_open_thread_loads_the_conversation_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $thread = 'u'.$user->id.'-01M1XBGGYBQ300JN9QXDD03K5E';

        $this->seedThread($thread, $user->id, [
            ['role' => 'user', 'text' => 'quanti pazienti ci sono'],
            ['role' => 'assistant', 'text' => null],
            ['role' => 'user', 'text' => null],
            ['role' => 'assistant', 'text' => "**Risultato:**\n\nCi sono **2.565 pazienti attivi**."],
        ]);

        $page = Livewire::test(DataAssistant::class);

        // Parte da una nuova conversazione vuota.
        $page->call('newConversation');
        $this->assertSame([], $page->get('messages'));

        // Cliccando il thread in sidebar la conversazione viene caricata.
        $page->call('openThread', $thread);

        $messages = $page->get('messages');
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertStringContainsString('quanti pazienti ci sono', $messages[0]['html']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertStringContainsString('2.565 pazienti attivi', $messages[1]['html']);
        $this->assertSame('quanti pazienti ci sono', $messages[1]['question']);
    }

    public function test_recent_threads_lists_only_the_authenticated_users_threads(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $mine = 'u'.$user->id.'-01AAAAAAAAAAAAAAAAAAAAAAAA';
        $theirs = 'u'.$other->id.'-01BBBBBBBBBBBBBBBBBBBBBBBB';

        $this->seedThread($mine, $user->id, [
            ['role' => 'user', 'text' => 'prima domanda'],
            ['role' => 'assistant', 'text' => 'prima risposta'],
        ]);
        $this->seedThread($theirs, $other->id, [
            ['role' => 'user', 'text' => 'domanda altrui'],
        ]);

        $threads = Livewire::test(DataAssistant::class)->instance()->recentThreads();

        $this->assertCount(1, $threads);
        $this->assertSame($mine, $threads[0]['thread_id']);
        $this->assertSame('prima domanda', $threads[0]['label']);
        $this->assertSame(2, $threads[0]['messages']);
    }

    public function test_open_thread_rejects_a_thread_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $theirs = 'u'.$other->id.'-01CCCCCCCCCCCCCCCCCCCCCCCC';
        $this->seedThread($theirs, $other->id, [
            ['role' => 'user', 'text' => 'segreto'],
            ['role' => 'assistant', 'text' => 'risposta segreta'],
        ]);

        $page = Livewire::test(DataAssistant::class);
        $page->call('newConversation');
        $page->call('openThread', $theirs);

        $this->assertSame([], $page->get('messages'));
        $this->assertNotSame($theirs, $page->get('thread'));
    }
}
