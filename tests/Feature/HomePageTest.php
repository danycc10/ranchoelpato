<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_la_portada_publica_responde_correctamente(): void
    {
        $this->get('/')
            ->assertOk();
    }
}
