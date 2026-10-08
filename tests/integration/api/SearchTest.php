<?php

namespace Ernestdefoe\Reel\Tests\integration\api;

use Ernestdefoe\Reel\Providers;
use Ernestdefoe\Reel\Tests\integration\FakeProvider;
use Ernestdefoe\Reel\Tests\integration\FakeProviders;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * GET /api/reel/search, the forum-side proxy that keeps the provider's API
 * key out of the browser.
 */
class SearchTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private FakeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-reel');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);

        $this->provider = new FakeProvider();
    }

    /** Boot the forum with the fake provider in place of GIPHY/KLIPY. */
    private function boot(): void
    {
        $container = $this->app()->getContainer();
        $container->instance(Providers::class, new FakeProviders($container->make(SettingsRepositoryInterface::class), $this->provider));
    }

    private function search(?int $actor, array $query = []): array
    {
        $this->boot();

        $response = $this->send(
            $this->request('GET', '/api/reel/search', $actor ? ['authenticatedAs' => $actor] : [])->withQueryParams($query)
        );

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    #[Test]
    public function guests_cannot_search()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');

        [$status] = $this->search(null, ['q' => 'cats']);

        $this->assertSame(403, $status);
        $this->assertSame([], $this->provider->calls);
    }

    #[Test]
    public function members_cannot_search_once_the_permission_is_revoked()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'reel.use')->delete();

        [$status] = $this->search(2, ['q' => 'cats']);

        $this->assertSame(403, $status);
        $this->assertSame([], $this->provider->calls);
    }

    #[Test]
    public function without_a_key_the_search_says_it_is_not_configured()
    {
        [$status, $body] = $this->search(2, ['q' => 'cats']);

        $this->assertSame(503, $status);
        $this->assertSame('reel_not_configured', $body['errors'][0]['code']);
    }

    #[Test]
    public function the_key_follows_the_chosen_provider()
    {
        // A GIPHY key does not configure KLIPY.
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');
        $this->setting('ernestdefoe-reel.provider', 'klipy');

        [$status] = $this->search(2, ['q' => 'cats']);

        $this->assertSame(503, $status);
    }

    #[Test]
    public function members_and_admins_get_results_with_the_providers_credit()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');

        foreach ([2, 1] as $actor) {
            [$status, $body] = $this->search($actor, ['q' => 'cats '.$actor]);

            $this->assertSame(200, $status);
            $this->assertSame('FAKE', $body['credit']);
            $this->assertSame('https://media.example/f.gif', $body['items'][0]['url']);
            $this->assertSame(24, $body['next']);
        }
    }

    #[Test]
    public function the_query_offset_and_rating_are_clamped_before_reaching_the_provider()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');
        $this->setting('ernestdefoe-reel.rating', 'nc-17');

        $this->search(2, ['q' => '  '.str_repeat('a', 150).'  ', 'offset' => '9999']);

        $this->assertSame([[
            'query' => str_repeat('a', 100),
            'offset' => 500,
            'limit' => 24,
            'rating' => 'pg-13',
            'locale' => 'en',
        ]], $this->provider->calls);
    }

    #[Test]
    public function a_repeated_search_is_answered_from_the_cache()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');

        $this->search(2, ['q' => 'Cats']);
        $this->search(2, ['q' => 'cats']);
        $this->search(1, ['q' => 'cats']);

        $this->assertCount(1, $this->provider->calls, 'One provider request for the same page of the same search');

        $this->search(2, ['q' => 'cats', 'offset' => 24]);
        $this->assertCount(2, $this->provider->calls, 'A different page is a different request');
    }

    #[Test]
    public function a_provider_failure_is_a_502_that_does_not_leak_the_key()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'SECRETKEY');
        $this->provider->fail = true;

        [$status, $body] = $this->search(2, ['q' => 'cats']);

        $this->assertSame(502, $status);
        $this->assertSame('reel_unavailable', $body['errors'][0]['code']);
        $this->assertStringNotContainsString('SECRETKEY', json_encode($body));
    }

    #[Test]
    public function a_member_is_throttled_after_thirty_searches_a_minute()
    {
        $this->setting('ernestdefoe-reel.giphy_key', 'KEY');

        // The throttle counts per clock minute. Start well clear of the next
        // one, so all 31 searches land in the same window.
        if (time() % 60 > 45) {
            sleep(61 - time() % 60);
        }

        for ($n = 1; $n <= 30; $n++) {
            [$status] = $this->search(2, ['q' => 'cats']);
            $this->assertSame(200, $status, "Search $n");
        }

        [$status] = $this->search(2, ['q' => 'cats']);
        $this->assertSame(429, $status);
    }
}
