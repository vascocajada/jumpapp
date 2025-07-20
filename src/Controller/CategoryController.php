<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\BaseController;

#[Route('/category')]
final class CategoryController extends BaseController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        
        $categories = $categoryRepository->findByUserWithPagination($this->getUser(), $page, $limit);
        $totalCategories = $categoryRepository->countByUser($this->getUser());
        $totalPages = ceil($totalCategories / $limit);
        
        return $this->renderWithConfig('category/index.html.twig', [
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCategories' => $totalCategories,
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setOwner($this->getUser());
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category "' . $category->getName() . '" created successfully!');
            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderWithConfig('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(Category $category, Request $request, \App\Repository\EmailRepository $emailRepository): Response
    {
        if ($category->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        
        // Get emails for this category with pagination
        $emails = $emailRepository->findByCategoryWithPagination($category, $this->getUser(), $page, $limit);
        $totalEmails = $emailRepository->countByCategory($category, $this->getUser());
        $totalPages = ceil($totalEmails / $limit);

        return $this->renderWithConfig('category/show.html.twig', [
            'category' => $category,
            'emails' => $emails,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalEmails' => $totalEmails,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($category->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Category "' . $category->getName() . '" updated successfully!');
            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderWithConfig('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($category->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $categoryName = $category->getName();
            $entityManager->remove($category);
            $entityManager->flush();
            $this->addFlash('success', 'Category "' . $categoryName . '" deleted successfully!');
        }

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
}
