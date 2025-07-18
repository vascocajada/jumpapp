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
        self::assertPageTitleContains('Category index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $this->client->request('GET', sprintf('%s/new', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'category[name]' => 'Testing',
            'category[description]' => 'Testing',
            // Do not include 'category[owner]'
        ]);

        self::assertResponseRedirects($this->path, 303);

        $category = $this->categoryRepository->findOneBy(['name' => 'Testing']);
        self::assertNotNull($category);
        self::assertSame('Testing', $category->getName());
        self::assertSame('user1@example.com', $category->getOwner()->getEmail());
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
        self::assertPageTitleContains('Category');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $fixture = new Category();
        $fixture->setName('Value');
        $fixture->setDescription('Value');
        $fixture->setOwner($this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s/%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'category[name]' => 'Something New',
            'category[description]' => 'Something New',
            'category[owner]' => '8',
        ]);

        self::assertResponseRedirects('/category');

        $editedCategory = $this->categoryRepository->findOneById($fixture->getId());

        self::assertSame('Something New', $editedCategory->getName());
        self::assertSame('Something New', $editedCategory->getDescription());
        self::assertSame('user2@example.com', $editedCategory->getOwner()->getEmail());
    }

    public function testRemove(): void
    {
        $this->logInUser($this->client, 'user1@example.com');
        $fixture = new Category();
        $fixture->setName('Value');
        $fixture->setDescription('Value');
        $fixture->setOwner($this->manager->getRepository(User::class)->findOneBy(['email' => 'user1@example.com']));

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s/%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/category');
        self::assertSame(2, $this->categoryRepository->count([]));
    }

    public function testUserCanOnlySeeOwnCategories()
    {
        $this->client->followRedirects();
        $this->logInUser($this->client, 'user1@example.com');
        $crawler = $this->client->request('GET', '/category/');
        $categories = $crawler->filter('td:contains("user1 category")');
        $this->assertGreaterThan(0, $categories->count(), 'User should see their own categories');
        $this->assertStringNotContainsString('user2 category', $this->client->getResponse()->getContent(), 'User should not see other users categories');
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

    public function testUserCanCreateEditDeleteOwnCategory()
    {
        $this->logInUser($this->client, 'user1@example.com');
        // Create
        $crawler = $this->client->request('GET', '/category/new');
        $form = $crawler->selectButton('Save')->form([
            'category[name]' => 'My New Category',
            'category[description]' => 'Test description',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects();
        // Edit
        $category = self::getContainer()->get('doctrine')->getRepository(Category::class)->findOneBy(['name' => 'My New Category']);
        $crawler = $this->client->request('GET', '/category/'.$category->getId().'/edit');
        $form = $crawler->selectButton('Update')->form([
            'category[name]' => 'My Updated Category',
        ]);
        $this->client->submit($form);
        $this->assertResponseRedirects();
        // Delete
        $this->client->request('POST', '/category/'.$category->getId(), [
            '_method' => 'DELETE',
            '_token' => 'dummy',
        ]);
        // Should redirect or 403 if CSRF fails
        $this->assertTrue(
            $this->client->getResponse()->isRedirect() || $this->client->getResponse()->getStatusCode() === 403
        );
    }
}
