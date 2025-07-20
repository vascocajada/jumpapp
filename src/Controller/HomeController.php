<?php

namespace App\Controller;

use App\Repository\GmailAccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;

class HomeController extends BaseController
{
    #[Route('/', name: 'app_home')]
    public function index(GmailAccountRepository $gmailAccountRepository, CategoryRepository $categoryRepository, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $config = $user->getConfig();
        } else {
            $config = null;
        }
        if (!$config) {
            $config = new \App\Entity\Config();
            if ($user instanceof \App\Entity\User) {
                $config->setUser($user);
            }
            $em->persist($config);
            $em->flush();
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        
        // Get user's Gmail accounts and categories with pagination
        $gmailAccounts = $gmailAccountRepository->findBy(['owner' => $this->getUser()]);
        $categories = $categoryRepository->findByUserWithPagination($this->getUser(), $page, $limit);
        $totalCategories = $categoryRepository->countByUser($this->getUser());
        $totalPages = ceil($totalCategories / $limit);

        return $this->renderWithConfig('home/dashboard.html.twig', [
            'gmailAccounts' => $gmailAccounts,
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCategories' => $totalCategories,
        ]);
    }

    #[Route('/notifications/mark-read', name: 'app_notifications_mark_read', methods: ['POST'])]
    public function markNotificationsRead(EntityManagerInterface $em, NotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }
        $notifications = $notificationRepository->findUnreadForUser($user);
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/config/toggle-list-unsubscribe', name: 'app_toggle_list_unsubscribe', methods: ['POST'])]
    public function toggleListUnsubscribe(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !$user instanceof \App\Entity\User) {
            $this->addFlash('error', 'You must be logged in.');
            return $this->redirectToRoute('app_home');
        }
        $config = $user->getConfig();
        if (!$config) {
            $config = new \App\Entity\Config();
            $config->setUser($user);
            $em->persist($config);
        }
        $config->setUseGmailListUnsubscribe(!$config->getUseGmailListUnsubscribe());
        $em->flush();
        $this->addFlash('success', 'Gmail List-Unsubscribe setting updated.');
        return $this->redirectToRoute('app_home');
    }
} 