<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OAuthControllerTest extends WebTestCase
{
    public function testConnectGoogleRouteRedirectsToGoogle()
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');
        $this->assertResponseRedirects();
        $redirectUrl = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $redirectUrl, 'Should redirect to Google OAuth');
    }

    public function testConnectGoogleCheckRouteIsNotDirectlyAccessible()
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google/check');
        $this->assertResponseStatusCodeSame(404, 'Should return 404 for direct access');
    }

    public function testConnectGoogleIsAccessibleWithoutAuthentication()
    {
        $client = static::createClient();
        $client->request('GET', '/connect/google');
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode(), '/connect/google should not require authentication');
    }
} 