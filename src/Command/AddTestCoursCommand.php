<?php

namespace App\Command;

use App\Entity\Cours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-test-cours',
    description: 'Ajoute des données de test dans la table cours',
)]
class AddTestCoursCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coursData = [
            [
                'titre' => 'Mathématiques 6ème année',
                'description' => 'Cours de mathématiques pour les élèves de 6ème : nombres décimaux, fractions, géométrie de base.',
                'niveau' => 6,
                'matiere' => 'Mathématiques',
                'image' => '01.jpg',
            ],
            [
                'titre' => 'Français - Grammaire et orthographe',
                'description' => 'Renforcement de la grammaire française, conjugaison, et exercices d\'orthographe.',
                'niveau' => 5,
                'matiere' => 'Français',
                'image' => '02.jpg',
            ],
            [
                'titre' => 'Sciences de la Vie et de la Terre',
                'description' => 'Découverte du corps humain, de l\'environnement et des écosystèmes.',
                'niveau' => 4,
                'matiere' => 'SVT',
                'image' => '03.jpg',
            ],
            [
                'titre' => 'Arabe - Lecture et écriture',
                'description' => 'Initiation à la lecture et à l\'écriture en arabe classique.',
                'niveau' => 1,
                'matiere' => 'Arabe',
                'image' => '04.jpg',
            ],
            [
                'titre' => 'Histoire-Géographie 3ème',
                'description' => 'Histoire contemporaine et géographie mondiale pour le brevet.',
                'niveau' => 9,
                'matiere' => 'Histoire-Géographie',
                'image' => '05.jpg',
            ],
        ];

        foreach ($coursData as $data) {
            $cours = new Cours();
            $cours->setTitre($data['titre']);
            $cours->setDescription($data['description']);
            $cours->setNiveau($data['niveau']);
            $cours->setMatiere($data['matiere']);
            $cours->setImage($data['image']);

            $this->entityManager->persist($cours);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d cours de test ont été ajoutés.', count($coursData)));

        return Command::SUCCESS;
    }
}
