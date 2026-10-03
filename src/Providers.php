<?php

namespace Ernestdefoe\Reel;

use Ernestdefoe\Reel\Provider\Giphy;
use Ernestdefoe\Reel\Provider\Klipy;
use Ernestdefoe\Reel\Provider\Provider;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;

/** The provider the admin chose, if it has a key. */
class Providers
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function name(): string
    {
        return $this->settings->get('ernestdefoe-reel.provider') === 'klipy' ? 'klipy' : 'giphy';
    }

    public function key(): string
    {
        return trim((string) $this->settings->get('ernestdefoe-reel.' . $this->name() . '_key', ''));
    }

    public function configured(): bool
    {
        return $this->key() !== '';
    }

    public function make(): ?Provider
    {
        if (! $this->configured()) {
            return null;
        }

        $http = new Client(['timeout' => 6, 'connect_timeout' => 3, 'headers' => ['User-Agent' => 'Reel for Flarum']]);

        return $this->name() === 'klipy' ? new Klipy($http, $this->key()) : new Giphy($http, $this->key());
    }

    public function rating(): string
    {
        $r = (string) $this->settings->get('ernestdefoe-reel.rating', 'pg-13');

        return in_array($r, ['g', 'pg', 'pg-13', 'r'], true) ? $r : 'pg-13';
    }
}
