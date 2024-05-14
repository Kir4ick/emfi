<?php

namespace App\AmoCRM;

use AmoCRM\OAuth\OAuthServiceInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;

class OAuthService implements OAuthServiceInterface
{

    /**
     * Не нужно, так как используется долгий ключ
     *
     * @param AccessTokenInterface $accessToken
     * @param string $baseDomain
     * @return void
     */
    public function saveOAuthToken(AccessTokenInterface $accessToken, string $baseDomain): void
    {}
}
