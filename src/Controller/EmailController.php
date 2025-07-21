<?php

namespace App\Controller;

use App\Entity\Email;
use App\Repository\EmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Message\UnsubscribeEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Controller\BaseController;
use App\Message\ImportEmailsMessage;

#[Route('/email')]
final class EmailController extends BaseController
{
    #[Route('/{id}', name: 'app_email_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(?Email $email): Response
    {
        if (!$email) {
            $this->addFlash('error', 'The requested email was not found.');
            return $this->redirectToRoute('app_home');
        }
        return $this->renderWithConfig('email/show.html.twig', [
            'email' => $email,
        ]);
    }



    #[Route('/{id}', name: 'app_email_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, ?Email $email, EntityManagerInterface $entityManager): Response
    {
        if (!$email) {
            $this->addFlash('error', 'The requested email was not found.');
            return $this->redirectToRoute('app_home');
        }
        if ($this->isCsrfTokenValid('delete'.$email->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($email);
            $entityManager->flush();
        }
        $category = $email->getCategory();
        if ($category) {
            return $this->redirectToRoute('app_category_show', ['id' => $category->getId()]);
        } else {
            return $this->redirectToRoute('app_home');
        }
    }

    #[Route('/{id}/unsubscribe', name: 'app_email_unsubscribe', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unsubscribe(Request $request, ?Email $email, MessageBusInterface $bus, \Psr\Log\LoggerInterface $logger): Response
    {
        if (!$email) {
            $this->addFlash('error', 'The requested email was not found.');
            return $this->redirectToRoute('app_home');
        }
        if (!$this->isCsrfTokenValid('unsubscribe' . $email->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_category_show', ['id' => $email->getCategory()->getId()]);
        }

        $category = $email->getCategory();
        $categoryId = $category ? $category->getId() : null;
        if (!$categoryId) {
            $this->addFlash('error', 'Email has no category.');
            return $this->redirectToRoute('app_home');
        }

        // Prevent dispatching if the email body is empty
        if (empty($email->getBody())) {
            $this->addFlash('error', 'Cannot unsubscribe: email body is empty.');
            return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
        }

        $logger->info('About to dispatch UnsubscribeEmailMessage', ['emailId' => $email->getId()]);
        $bus->dispatch(new UnsubscribeEmailMessage($email->getId()));
        $logger->info('Dispatched UnsubscribeEmailMessage', ['emailId' => $email->getId()]);
        $this->addFlash('info', 'Unsubscribe requested. We’ll notify you when it’s done.');
        return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
    }

    #[Route('/import-emails', name: 'app_import_emails', methods: ['POST'])]
    public function importEmails(MessageBusInterface $bus): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $userId = method_exists($user, 'getId') ? $user->getId() : $user->getUserIdentifier();
        $bus->dispatch(new ImportEmailsMessage($userId));
        $this->addFlash('success', 'Email import started in the background.');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/bulk-delete', name: 'app_email_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager, EmailRepository $emailRepository): Response
    {
        $emailIds = $request->getPayload()->all('email_ids') ?: [];
        $categoryId = $request->getPayload()->get('category_id');
        
        if (empty($emailIds)) {
            $this->addFlash('error', 'No emails selected for deletion.');
            return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
        }
        
        // Verify all emails belong to the current user
        $emails = $emailRepository->findBy([
            'id' => $emailIds,
            'owner' => $this->getUser()
        ]);
        
        if (count($emails) !== count($emailIds)) {
            $this->addFlash('error', 'Some emails could not be found or you do not have permission to delete them.');
            return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
        }
        
        // Delete the emails
        foreach ($emails as $email) {
            $entityManager->remove($email);
        }
        $entityManager->flush();
        
        $this->addFlash('success', count($emails) . ' email(s) deleted successfully.');
        return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
    }

    #[Route('/bulk-unsubscribe', name: 'app_email_bulk_unsubscribe', methods: ['POST'])]
    public function bulkUnsubscribe(Request $request, EmailRepository $emailRepository, MessageBusInterface $bus, \Psr\Log\LoggerInterface $logger): Response
    {
        $emailIds = $request->getPayload()->all('email_ids') ?: [];
        $categoryId = $request->getPayload()->get('category_id');
        
        if (empty($emailIds)) {
            $this->addFlash('error', 'No emails selected for unsubscribe.');
            return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
        }
        
        // Verify all emails belong to the current user
        $emails = $emailRepository->findBy([
            'id' => $emailIds,
            'owner' => $this->getUser()
        ]);
        
        if (count($emails) !== count($emailIds)) {
            $this->addFlash('error', 'Some emails could not be found or you do not have permission to unsubscribe them.');
            return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
        }
        
        foreach ($emails as $email) {
            $logger->info('Bulk unsubscribe: dispatching', ['emailId' => $email->getId()]);
            $bus->dispatch(new UnsubscribeEmailMessage($email->getId()));
        }
        
        $this->addFlash('info', count($emails) . ' unsubscribe request(s) queued.');
        return $this->redirectToRoute('app_category_show', ['id' => $categoryId]);
    }
}
