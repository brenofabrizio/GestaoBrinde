<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_new_application_timestamps_use_sao_paulo_timezone(): void
    {
        $this->assertSame('America/Sao_Paulo', now()->timezoneName);
    }
}
