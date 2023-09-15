<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
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
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookController extends AbstractController
{
    /**
     * @throws InvalidArgumentException in cache
     */
    #[Route('/api/books', name: 'app_books', methods: ['GET'])]
    public function getBooks(BookRepository         $bookRepository,
                             SerializerInterface    $serializer,
                             Request                $request,
                             PaginatorInterface     $paginator,
                             TagAwareCacheInterface $cache): JsonResponse
    {
        $books = $bookRepository->findAll();

        $offset = $request->query->getInt('offset', 1);
        $limit = $request->query->getInt('limit', 5);

        $idCache = 'getBooks-' . $offset . '-' . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($paginator, $books, $offset, $limit, $serializer) {
            echo('L\'élément n\'est pas encore en cache !'); // displays on postman if we went through the function
            $item->tag('booksCache')
                ->expiresAfter(60);
            $booksPaginated = $paginator->paginate($books, $offset, $limit);

            return $serializer->serialize($booksPaginated, 'json', ['groups' => 'getBooks']);
        });

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'app_books_details', methods: ['GET'])]
    public function getBookDetail(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException in invalidateTags
     */
    #[Route('/api/books/{id}', name: 'app_books_delete', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(['booksCache']);
        $entityManager->remove($book);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name: 'app_books_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre.')]
    public function createBook(Request                $request,
                               SerializerInterface    $serializer,
                               EntityManagerInterface $entityManager,
                               UrlGeneratorInterface  $urlGenerator,
                               AuthorRepository       $authorRepository,
                               ValidatorInterface     $validator
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

    /**
     * @throws InvalidArgumentException in invalidateTags
     */
    #[Route('/api/books/{id}', name: 'app_books_update', methods: ['PATCH'])]
    public function updateBook(Request                $request,
                               SerializerInterface    $serializer,
                               Book                   $currentBook,
                               EntityManagerInterface $entityManager,
                               ValidatorInterface     $validator,
                               TagAwareCacheInterface $cache
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

        $cache->invalidateTags(['booksCache']);
        $entityManager->persist($updatedBook);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
