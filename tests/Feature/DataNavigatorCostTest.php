<?php

namespace Tests\Feature;

use App\Neuron\CachedAnthropic;
use App\Neuron\DataNavigatorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DataNavigatorCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_is_sent_as_a_cacheable_block(): void
    {
        $provider = (new CachedAnthropic(key: 'test', model: 'claude-sonnet-4-6'))->withPromptCaching();
        $provider->systemPrompt('Istruzioni e schema');

        $blocks = (new ReflectionProperty($provider, 'systemBlocks'))->getValue($provider);

        $this->assertSame([
            ['type' => 'text', 'text' => 'Istruzioni e schema', 'cache_control' => ['type' => 'ephemeral']],
        ], $blocks);
    }

    public function test_schema_tool_is_limited_to_the_profile_tables_and_their_lookups(): void
    {
        $method = new ReflectionMethod(DataNavigatorAgent::class, 'inspectableTables');

        $this->assertSame(['centers', 'patients', 'patient_visits'], $method->invoke(new DataNavigatorAgent));
    }
}
