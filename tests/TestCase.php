<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le rotte del pannello sono prefissate dall'azienda (`admin/{tenant}/...`):
        // i test che montano i componenti senza richiesta HTTP non hanno un tenant
        // corrente, quindi forniamo un valore di default solo per generare gli URL.
        URL::defaults(['tenant' => 1]);
    }
}
