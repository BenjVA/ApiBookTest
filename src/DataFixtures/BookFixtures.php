<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private AuthorRepository $authorRepository)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $author = $this->authorRepository->findAll();
        // Création d'une vingtaine de livres ayant pour titre l'incrémentation
        for ($i = 0; $i < 20; $i++) {
            $livre = new Book;
            $livre->setTitle('Livre ' . $i);
            $livre->setCoverText('Quatrième de couverture numéro : ' . $i);
            $livre->setAuthor($author[mt_rand(0, 9)]);
            $livre->setComment('Commentaire' . $i);
            $manager->persist($livre);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
          AuthorFixtures::class
        ];
    }
}
