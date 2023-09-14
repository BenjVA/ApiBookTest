<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookController extends AbstractController
{
    #[Route('/api/books', name: 'app_books', methods: ['GET'])]
    public function getBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, PaginatorInterface $paginator): JsonResponse
    {
        $books = $bookRepository->findAll();

        $bookList = $paginator->paginate($books, $request->query->getInt('page', 1), 3);

        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'app_books_details', methods: ['GET'])]
    public function getBookDetail(Book $book, SerializerInterface $serializer): JsonResponse
    {
            $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

            return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'app_books_delete', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $entityManager): JsonResponse
    {
            $entityManager->remove($book);
            $entityManager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'app_books_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre.')]
    public function createBook(Request $request,
                               SerializerInterface $serializer,
                               EntityManagerInterface $entityManager,
                               UrlGeneratorInterface $urlGenerator,
                               AuthorRepository $authorRepository,
                               ValidatorInterface $validator
    ): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($book);
        $entityManager->flush();

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate('app_books_details', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    #[Route('/api/books/{id}', name: 'app_books_update', methods: ['PATCH'])]
    public function updateBook(Request $request,
                               SerializerInterface $serializer,
                               Book $currentBook,
                               EntityManagerInterface $entityManager,
                               ValidatorInterface $validator
    ): JsonResponse
    {
        $updatedBook = $serializer->deserialize(
            $request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentBook]
        );

        $errors = $validator->validate($updatedBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($updatedBook);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
