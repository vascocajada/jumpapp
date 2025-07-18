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

    public function testIndex(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);
        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Email index');
    }

    public function testNew(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $category = $this->manager->getRepository(Category::class)->findOneBy(['name' => 'user1 category']);
        $this->client->request('GET', sprintf('%s/new', $this->path));
        self::assertResponseStatusCodeSame(200);
        $this->client->submitForm('Save', [
            'email[subject]' => 'Test Subject',
            'email[sender]' => 'sender@example.com',
            'email[body]' => 'Test body',
            'email[summary]' => 'Test summary',
            'email[receivedAt]' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'email[gmailId]' => 'gmailid123',
            'email[category]' => $category ? $category->getId() : null,
        ]);
        self::assertResponseRedirects($this->path, 303);
        $email = $this->emailRepository->findOneBy(['subject' => 'Test Subject']);
        self::assertNotNull($email);
        self::assertSame('sender@example.com', $email->getSender());
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

    public function testEdit(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $category = $this->manager->getRepository(Category::class)->findOneBy(['name' => 'user1 category']);
        $user = $this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']);
        $email = new Email();
        $email->setSubject('Edit Subject');
        $email->setSender('edit@example.com');
        $email->setBody('Edit body');
        $email->setSummary('Edit summary');
        $email->setReceivedAt(new \DateTimeImmutable());
        $email->setGmailId('gmailid-edit');
        $email->setOwner($user);
        $email->setCategory($category);
        $this->manager->persist($email);
        $this->manager->flush();
        $this->client->request('GET', sprintf('%s/%s/edit', $this->path, $email->getId()));
        $this->client->submitForm('Update', [
            'email[subject]' => 'Updated Subject',
            'email[sender]' => 'updated@example.com',
            'email[body]' => 'Updated body',
            'email[summary]' => 'Updated summary',
        ]);
        self::assertResponseRedirects('/email');
        $editedEmail = $this->emailRepository->find($email->getId());
        self::assertSame('Updated Subject', $editedEmail->getSubject());
        self::assertSame('updated@example.com', $editedEmail->getSender());
    }

    public function testRemove(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $category = $this->manager->getRepository(Category::class)->findOneBy(['name' => 'user1 category']);
        $user = $this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']);
        $email = new Email();
        $email->setSubject('Delete Subject');
        $email->setSender('delete@example.com');
        $email->setBody('Delete body');
        $email->setSummary('Delete summary');
        $email->setReceivedAt(new \DateTimeImmutable());
        $email->setGmailId('gmailid-delete');
        $email->setOwner($user);
        $email->setCategory($category);
        $this->manager->persist($email);
        $this->manager->flush();
        $this->client->request('GET', sprintf('%s/%s', $this->path, $email->getId()));
        $this->client->submitForm('Delete');
        self::assertResponseRedirects('/email');
        self::assertNull($this->emailRepository->find($email->getId()));
    }
}
