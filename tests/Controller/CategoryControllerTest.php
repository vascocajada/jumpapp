<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $categoryRepository;
    private string $path = '/category';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->categoryRepository = $this->manager->getRepository(Category::class);

        // delete all categories that are not user1 category or user2 category
        $this->manager->getRepository(Category::class)->createQueryBuilder('c')
            ->delete()
            ->where('c.name NOT IN (:names)')
            ->setParameter('names', ['user1 category', 'user2 category'])
            ->getQuery()
            ->execute();
    }


    private function logInUser($client, $email = 'user1@example.com')
    {
        // You may need to adjust this for your actual login mechanism
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
        self::assertPageTitleContains('Categories');
    }

    public function testShow(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $fixture = new Category();
        $fixture->setName('My Title');
        $fixture->setDescription('My Title');
        $fixture->setOwner($this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s/%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('My Title');
    }

    public function testUserCannotAccessOthersCategoryShow()
    {
        $this->logInUser($this->client, 'user1@example.com');
        $otherCategory = self::getContainer()->get('doctrine')->getRepository(Category::class)->findOneBy(['name' => 'user2 category']);
        $this->client->request('GET', '/category/'.$otherCategory->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserCannotEditOthersCategory()
    {
        $this->logInUser($this->client, 'user1@example.com');
        $otherCategory = self::getContainer()->get('doctrine')->getRepository(Category::class)->findOneBy(['name' => 'user2 category']);
        $this->client->request('GET', '/category/'.$otherCategory->getId().'/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserCannotDeleteOthersCategory()
    {
        $this->logInUser($this->client, 'user1@example.com');
        $otherCategory = self::getContainer()->get('doctrine')->getRepository(Category::class)->findOneBy(['name' => 'user2 category']);
        $this->client->request('POST', '/category/'.$otherCategory->getId(), [
            '_method' => 'DELETE',
            '_token' => 'dummy',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
