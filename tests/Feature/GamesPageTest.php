<?php

namespace Tests\Feature;

use Tests\TestCase;

class GamesPageTest extends TestCase
{
    public function test_games_page_renders_hub(): void
    {
        $this->get('/jeux')
            ->assertOk()
            ->assertSee('gamesHub()', false)
            ->assertSee('Mini-jeux');
    }

    public function test_games_page_exposes_all_games(): void
    {
        $this->get('/jeux')
            ->assertOk()
            ->assertSee('gameDactylo()', false)
            ->assertSee('gameAnagram()', false)
            ->assertSee('gameCatch()', false)
            ->assertSee('gameRps()', false)
            ->assertSee('gameMorpion()', false)
            ->assertSee('gameMemory()', false)
            ->assertSee('gameSnake()', false);
    }

    public function test_games_page_switches_locale(): void
    {
        $this->get('/jeux?lang=en')
            ->assertOk()
            ->assertSee('Mini-games')
            ->assertDontSee('Mini-jeux')
            ->assertSee('Dactylo Arcade');
    }
}
