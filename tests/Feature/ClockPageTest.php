<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ClockPageTest extends TestCase
{
    public function test_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Horloge Mada')
            ->assertSee('Chat du shift');
    }

    public function test_health_check_up(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_sitemap_xml_renders(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<?xml version="1.0"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee(url('/'));
    }

    public function test_robots_txt_declares_sitemap(): void
    {
        $content = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap:', $content);
        $this->assertStringContainsString('/sitemap.xml', $content);
    }

    public function test_chat_send_stores_message_in_cache(): void
    {
        Cache::forget('chat:messages');

        Livewire::test('chat')
            ->set('username', 'Dada')
            ->set('message', 'Salut les collègues !')
            ->call('send')
            ->assertSet('message', '');

        $messages = Cache::get('chat:messages');
        $this->assertCount(1, $messages);
        $this->assertSame('Dada', $messages[0]['user']);
        $this->assertSame('Salut les collègues !', $messages[0]['text']);
    }

    public function test_chat_uses_anonymous_username_when_empty(): void
    {
        Cache::forget('chat:messages');

        Livewire::test('chat')
            ->set('message', 'Bonjour')
            ->call('send');

        $messages = Cache::get('chat:messages');
        $this->assertSame('Anonyme', $messages[0]['user']);
    }

    public function test_chat_ttl_expires_at_end_of_shift(): void
    {
        $tz = config('shift.timezone');
        $end = config('shift.end');

        $now = Carbon::parse('2026-08-10 10:00:00', $tz);
        Carbon::setTestNow($now);

        $component = Livewire::test('chat')->instance();
        $ttl = $this->invokeProtectedTtl($component);

        $expected = (int) $now->diffInSeconds(Carbon::parse('2026-08-10 '.$end, $tz));
        $this->assertSame($expected, $ttl);

        $afterEnd = Carbon::parse('2026-08-10 '.$end, $tz)->addHour();
        Carbon::setTestNow($afterEnd);

        $component = Livewire::test('chat')->instance();
        $ttl = $this->invokeProtectedTtl($component);

        $nextEnd = Carbon::parse('2026-08-11 '.$end, $tz);
        $this->assertSame((int) $afterEnd->diffInSeconds($nextEnd), $ttl);

        Carbon::setTestNow();
    }

    public function test_locale_query_param_switches_language(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('Shift chat')
            ->assertSee('Work day')
            ->assertDontSee('Chat du shift');
    }

    public function test_locale_cookie_switches_language(): void
    {
        $this->withUnencryptedCookie('locale', 'mg')
            ->get('/')
            ->assertOk()
            ->assertSee('Andro fiasana')
            ->assertSee('Ora eto Madagasikara');
    }

    public function test_invalid_locale_falls_back_to_default(): void
    {
        $this->get('/?lang=xx')
            ->assertOk()
            ->assertSee('Chat du shift');
    }

    private function invokeProtectedTtl(object $component): int
    {
        $method = new \ReflectionMethod($component, 'ttl');
        $method->setAccessible(true);

        return $method->invoke($component);
    }
}
