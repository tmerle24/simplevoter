<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Poll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function createEventPoll(): array
    {
        $event = Event::create([]);
        $poll = Poll::create(['event_id' => $event->id, 'question' => 'Frage?']);
        $event->update(['active_poll_id' => $poll->id]);

        return [$event, $poll];
    }

    public function test_branding_is_stored_on_event_and_delivered_publicly(): void
    {
        Storage::fake('public');
        [$event] = $this->createEventPoll();

        $this->post("/p/{$event->manage_token}/edit/branding", [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 100),
            'primary_color' => '#1A2B3C',
            'accent_color' => '#ff0000',
        ])->assertOk()->assertJsonPath('branding.primary_color', '#1a2b3c');

        $event->refresh();
        Storage::disk('public')->assertExists($event->brand_logo_path);

        $this->getJson("/w/{$event->public_token}/state")
            ->assertJsonPath('branding.logo_url', '/storage/'.$event->brand_logo_path)
            ->assertJsonPath('branding.accent_color', '#ff0000');
    }

    public function test_logo_is_replaced_removed_and_deleted_with_event(): void
    {
        Storage::fake('public');
        [$event] = $this->createEventPoll();
        $url = "/p/{$event->manage_token}/edit/branding";

        $this->post($url, ['logo' => UploadedFile::fake()->image('a.png')])->assertOk();
        $first = $event->refresh()->brand_logo_path;

        $this->post($url, ['logo' => UploadedFile::fake()->image('b.png')])->assertOk();
        $second = $event->refresh()->brand_logo_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->post($url, ['remove_logo' => '1'])->assertOk()->assertJsonPath('branding.logo_url', null);
        Storage::disk('public')->assertMissing($second);

        $this->post($url, ['logo' => UploadedFile::fake()->image('c.png')])->assertOk();
        $third = $event->refresh()->brand_logo_path;
        $event->delete();
        Storage::disk('public')->assertMissing($third);
    }

    public function test_invalid_input_is_rejected(): void
    {
        Storage::fake('public');
        [$event] = $this->createEventPoll();
        $url = "/p/{$event->manage_token}/edit/branding";
        $json = ['Accept' => 'application/json'];

        $this->postJson($url, ['primary_color' => 'red'])->assertStatus(422);
        $this->post($url, [
            'logo' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ], $json)->assertStatus(422);
        $this->post($url, ['logo' => UploadedFile::fake()->image('big.png')->size(3000)], $json)->assertStatus(422);
        $this->assertNull($event->refresh()->brand_logo_path);
    }
}
