<?php

namespace App\Controller;

use App\Repository\GmailAccountRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(GmailAccountRepository $gmailAccountRepository, CategoryRepository $categoryRepository): Response
    {
        // If user is not authenticated, show welcome page
        if (!$this->getUser()) {
            return $this->render('home/index.html.twig');
        }

        // Get user's Gmail accounts and categories
        $gmailAccounts = $gmailAccountRepository->findBy(['owner' => $this->getUser()]);
        $categories = $categoryRepository->findBy(['owner' => $this->getUser()]);

        return $this->render('home/dashboard.html.twig', [
            'gmail_accounts' => $gmailAccounts,
            'categories' => $categories,
        ]);
    }
} 