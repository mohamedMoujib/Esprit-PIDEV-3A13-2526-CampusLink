<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Service;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du service',
                'required' => true,
                'attr' => ['placeholder' => 'Ex: Cours de Mathématiques']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Décrivez votre service en détail', 'rows' => 5]
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (DT)',
                'required' => true,
                'scale' => 2,
                'attr' => ['placeholder' => '0.00', 'step' => '0.01', 'min' => '0']
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Choisir une catégorie'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer le service',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
