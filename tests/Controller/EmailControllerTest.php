<?php

namespace App\Tests\Controller;

use App\Entity\Email;
use App\Entity\User;
use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EmailControllerTest extends WebTestCase
{
    private string $path = '/email';

    private function logInUser($client, $email = 'user1@example.com')
    {
        $container = $client->getContainer();
        $user = $container->get('doctrine')->getManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);
        return $user;
    }

    public function testImportEmailsDispatchesMessageAndRedirects(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']);
        $client->loginUser($user);
        $client->request('POST', '/email/import-emails');
        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('.fixed', 'Email import started in the background.');
        $envelopes = $container->get('messenger.transport.async')->get();
        $this->assertNotEmpty($envelopes);
        $this->assertInstanceOf(\App\Message\ImportEmailsMessage::class, $envelopes[0]->getMessage());
    }
}
