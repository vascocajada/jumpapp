<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // User 1
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setRoles(['ROLE_USER']);
        $user1->setName('User One');
        $manager->persist($user1);

        // User 2
        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setRoles(['ROLE_USER']);
        $user2->setName('User Two');
        $manager->persist($user2);

        // Category for user1
        $cat1 = new Category();
        $cat1->setName('user1 category');
        $cat1->setDescription('Category for user1');
        $cat1->setOwner($user1);
        $manager->persist($cat1);

        // Category for user2
        $cat2 = new Category();
        $cat2->setName('user2 category');
        $cat2->setDescription('Category for user2');
        $cat2->setOwner($user2);
        $manager->persist($cat2);

        $manager->flush();
    }
} 