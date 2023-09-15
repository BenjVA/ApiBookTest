<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
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
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AuthorController extends AbstractController
{
    /**
     * @throws InvalidArgumentException in cache->get()
     */
    #[Route('/api/authors', name: 'app_authors', methods: ['GET'])]
    public function getAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, PaginatorInterface $paginator): JsonResponse
    {
        $authors = $authorRepository->findAll();

        $offset = $request->query->getInt('offset', 1);
        $limit = $request->query->getInt('limit', 5);

        $idCache = 'getAuthors-' . $offset . '-' . $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($paginator, $authors, $offset, $limit, $serializer) {
            echo('L\'élément n\'est pas encore en cache !'); // displays on postman if we went through the function
            $item->tag('authorsCache')
                ->expiresAfter(60);
            $authorsPaginated = $paginator->paginate($authors, $offset, $limit);

            $context = SerializationContext::create()->setGroups(['getAuthors']);

            return $serializer->serialize($authorsPaginated, 'json', $context);
        });

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name: 'app_authors_details', methods: ['GET'])]
    public function getAuthorsDetails(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    /**
     * @throws InvalidArgumentException in invalidateTags
     */
    #[Route('/api/authors/{id}', name: 'app_authors_delete', methods: ['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(['authorsCache']);
        $entityManager->remove($author);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors', name: 'app_authors_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un auteur.')]
    public function createAuthor(Request                $request,
                                 SerializerInterface    $serializer,
                                 EntityManagerInterface $entityManager,
                                 UrlGeneratorInterface  $urlGenerator,
                                 ValidatorInterface     $validator
    ): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($author);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        $location = $urlGenerator->generate('app_authors_details', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * @throws InvalidArgumentException in invalidateTags
     */
    #[Route('/api/authors/{id}', name: 'app_authors_update', methods: ['PATCH'])]
    public function updateAuthor(Request                $request,
                                 SerializerInterface    $serializer,
                                 Author                 $currentAuthor,
                                 EntityManagerInterface $entityManager,
                                 ValidatorInterface     $validator,
                                 TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $context = DeserializationContext::create();
        $context->setAttribute('deserialization-constructor-target', $currentAuthor);
        $updatedAuthor = $serializer->deserialize(
            $request->getContent(),
            get_class($currentAuthor),
            'json'
        );

        $errors = $validator->validate($updatedAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $cache->invalidateTags(['authorsCache']);
        $entityManager->persist($updatedAuthor);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
