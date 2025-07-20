<?php

namespace App\Controller;

use App\Entity\GmailAccount;
use App\Repository\GmailAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gmail-accounts')]
final class GmailAccountController extends AbstractController
{
    #[Route('/', name: 'app_gmail_account_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/add', name: 'app_gmail_account_add', methods: ['GET'])]
    public function add(GmailAccountRepository $gmailAccountRepository): Response
    {
        // Check if user has reached the limit of 15 Gmail accounts
        $userAccounts = $gmailAccountRepository->findBy(['owner' => $this->getUser()]);
        if (count($userAccounts) >= 15) {
            $this->addFlash('error', 'You have reached the maximum limit of 15 Gmail accounts.');
            return $this->redirectToRoute('app_home');
        }
        
        // Redirect to a special OAuth route for adding Gmail accounts
        return $this->redirectToRoute('connect_google_add_account');
    }

    #[Route('/{id}', name: 'app_gmail_account_delete', methods: ['POST'])]
    public function delete(Request $request, GmailAccount $gmailAccount, EntityManagerInterface $entityManager): Response
    {
        if ($gmailAccount->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$gmailAccount->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($gmailAccount);
            $entityManager->flush();
            $this->addFlash('success', 'Gmail account removed successfully.');
        }

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
} 