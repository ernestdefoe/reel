<?php

namespace Ernestdefoe\Reel\Tests\integration;

use Ernestdefoe\Reel\Provider\Provider;
use Ernestdefoe\Reel\Providers;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The real provider choice, key and rating logic, handing out the fake
 * instead of an HTTP client, so no test ever calls a real GIF service.
 */
class FakeProviders extends Providers
{
    public function __construct(SettingsRepositoryInterface $settings, private FakeProvider $provider)
    {
        parent::__construct($settings);
    }

    public function make(): ?Provider
    {
        return $this->configured() ? $this->provider : null;
    }
}
