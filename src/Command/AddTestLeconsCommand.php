<?php

namespace App\Command;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Repository\CoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-test-lecons',
    description: 'Ajoute des données de test dans la table lecon',
)]
class AddTestLeconsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CoursRepository $coursRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coursList = $this->coursRepository->findBy([], ['id' => 'ASC'], 5);
        if (empty($coursList)) {
            $io->warning('Aucun cours trouvé. Exécutez d\'abord : php bin/console app:add-test-cours');
            return Command::FAILURE;
        }

        $leconsData = [
            ['cours' => 0, 'titre' => 'Introduction aux nombres décimaux', 'ordre' => 1, 'media_url' => 'https://example.com/pdf/maths-decimaux.pdf'],
            ['cours' => 0, 'titre' => 'Les fractions simples', 'ordre' => 2, 'media_url' => 'https://example.com/pdf/maths-fractions.pdf'],
            ['cours' => 0, 'titre' => 'Géométrie : triangles et périmètres', 'ordre' => 3, 'media_url' => 'https://example.com/pdf/maths-geometrie.pdf'],
            ['cours' => 1, 'titre' => 'La conjugaison au présent', 'ordre' => 1, 'media_url' => 'https://example.com/pdf/fr-conjugaison.pdf'],
            ['cours' => 1, 'titre' => 'Les accords dans le groupe nominal', 'ordre' => 2, 'media_url' => 'https://example.com/pdf/fr-accords.pdf'],
            ['cours' => 1, 'titre' => 'Dictée et orthographe', 'ordre' => 3, 'media_url' => 'https://example.com/pdf/fr-dictee.pdf'],
            ['cours' => 2, 'titre' => 'Le système digestif', 'ordre' => 1, 'media_url' => 'https://example.com/pdf/svt-digestif.pdf'],
            ['cours' => 2, 'titre' => 'La chaîne alimentaire', 'ordre' => 2, 'media_url' => 'https://example.com/pdf/svt-chaine.pdf'],
            ['cours' => 3, 'titre' => 'L\'alphabet arabe', 'ordre' => 1, 'media_url' => 'https://example.com/pdf/arabe-alphabet.pdf'],
            ['cours' => 3, 'titre' => 'Lecture des voyelles', 'ordre' => 2, 'media_url' => 'https://example.com/pdf/arabe-voyelles.pdf'],
            ['cours' => 4, 'titre' => 'La Première Guerre mondiale', 'ordre' => 1, 'media_url' => 'https://example.com/pdf/hg-ww1.pdf'],
            ['cours' => 4, 'titre' => 'Géographie : les reliefs', 'ordre' => 2, 'media_url' => 'https://example.com/pdf/hg-reliefs.pdf'],
        ];

        $count = 0;
        foreach ($leconsData as $data) {
            $coursIndex = min($data['cours'], count($coursList) - 1);
            $cours = $coursList[$coursIndex];

            $lecon = new Lecon();
            $lecon->setCours($cours);
            $lecon->setTitre($data['titre']);
            $lecon->setOrdre($data['ordre']);
            $lecon->setMediaType('pdf_video');
            $lecon->setMediaUrl($data['media_url']);

            $this->entityManager->persist($lecon);
            $count++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d leçons de test ont été ajoutées.', $count));

        return Command::SUCCESS;
    }
}
