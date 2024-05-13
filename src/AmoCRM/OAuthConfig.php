<?php

namespace App\AmoCRM;

use AmoCRM\OAuth\OAuthConfigInterface;

class OAuthConfig implements OAuthConfigInterface
{

    public function __construct(
        private readonly string $integrationID,
        private readonly string $secretKey,
        private readonly string $redirectDomain,
    )
    {}

    public function getIntegrationId(): string
    {
        return $this->integrationID;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getRedirectDomain(): string
    {
        return $this->redirectDomain;
    }
}
