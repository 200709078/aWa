<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_bulunamayan_sayfa_turkce_404_gosterir(): void
    {
        $this->get('/olmayan-sayfa-xyz')->assertNotFound()->assertSee('Sayfa bulunamadı', false);
    }
}
