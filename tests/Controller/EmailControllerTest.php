<?php

namespace App\Tests\Controller;

use App\Entity\Email;
use App\Entity\User;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class EmailControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $emailRepository;
    private string $path = '/email';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->emailRepository = $this->manager->getRepository(Email::class);
        // Optionally clear emails for a clean state
        $this->manager->createQuery('DELETE FROM App\\Entity\\Email')->execute();
    }

    private function logInUser($client, $email = 'user1@example.com')
    {
        $user = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email' => $email]);
        $client->loginUser($user);
        return $user;
    }

    public function testShow(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $category = $this->manager->getRepository(Category::class)->findOneBy(['name' => 'user1 category']);
        $user = $this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']);
        $email = new Email();
        $email->setSubject('Show Subject');
        $email->setSender('show@example.com');
        $email->setBody('Show body');
        $email->setSummary('Show summary');
        $email->setReceivedAt(new \DateTimeImmutable());
        $email->setGmailId('gmailid-show');
        $email->setOwner($user);
        $email->setCategory($category);
        $this->manager->persist($email);
        $this->manager->flush();
        $this->client->request('GET', sprintf('%s/%s', $this->path, $email->getId()));
        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Email');
        self::assertStringContainsString('Show Subject', $this->client->getResponse()->getContent());
    }
}
