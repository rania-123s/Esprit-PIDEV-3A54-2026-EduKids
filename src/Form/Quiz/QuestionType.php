<?php

namespace App\Form\Quiz;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\QuestionOption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('texte', TextareaType::class, [
                'label' => 'Énoncé de la question',
                'attr' => ['rows' => 3, 'placeholder' => 'Posez votre question...'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'QCM (choix multiples)' => Question::TYPE_QCM,
                    'Réponse libre (texte)' => Question::TYPE_TEXTE,
                ],
            ])
            ->add('questionOptions', CollectionType::class, [
                'label' => false,
                'entry_type' => QuestionOptionType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'attr' => ['class' => 'question-options-collection'],
            ])
            ->add('bonneReponse', TextType::class, [
                'label' => 'Réponse attendue (texte)',
                'required' => false,
                'help' => 'Pour type "Réponse libre" uniquement.',
                'attr' => ['placeholder' => 'Ex: Paris'],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'data' => $options['ordre_default'] ?? 0,
                'attr' => ['min' => 0],
            ])
        ;

        // Ensure only one option is marked correct for QCM
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $question = $event->getData();
            if (!$question instanceof Question || $question->getType() !== Question::TYPE_QCM) {
                return;
            }
            $firstCorrect = null;
            foreach ($question->getQuestionOptions() as $opt) {
                if ($opt->isCorrect()) {
                    if ($firstCorrect === null) {
                        $firstCorrect = $opt;
                    } else {
                        $opt->setCorrect(false);
                    }
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
            'ordre_default' => 0,
        ]);
        $resolver->setAllowedTypes('ordre_default', 'int');
    }
}
