<?php

namespace App\DataFixtures;

use App\Entity\Author;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AuthorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création des auteurs
        for ($i = 0; $i < 10; $i++) {
            $author = new Author();
            $author->setFirstName('Prénom' . $i);
            $author->setLastName('Nom' . $i);
            $manager->persist($author);
        }

        $manager->flush();
    }
}
