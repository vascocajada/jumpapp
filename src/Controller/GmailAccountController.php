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
    public function index(GmailAccountRepository $gmailAccountRepository): Response
    {
        $accounts = $gmailAccountRepository->findBy(['owner' => $this->getUser()]);
        
        return $this->render('gmail_account/index.html.twig', [
            'gmail_accounts' => $accounts,
        ]);
    }

    #[Route('/add', name: 'app_gmail_account_add', methods: ['GET'])]
    public function add(): Response
    {
        // Redirect to a special OAuth route for adding Gmail accounts
        return $this->redirectToRoute('connect_google_add_account');
    }

    #[Route('/{id}', name: 'app_gmail_account_show', methods: ['GET'])]
    public function show(GmailAccount $gmailAccount): Response
    {
        if ($gmailAccount->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('gmail_account/show.html.twig', [
            'gmail_account' => $gmailAccount,
        ]);
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

        return $this->redirectToRoute('app_gmail_account_index', [], Response::HTTP_SEE_OTHER);
    }
} 