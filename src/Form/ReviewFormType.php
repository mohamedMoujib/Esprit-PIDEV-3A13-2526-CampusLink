<?php

namespace App\Form;

use App\Entity\Review;
use Boruta\StarRatingBundle\Form\StarRatingType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', StarRatingType::class, [
                'label' => 'Votre note',
                'stars' => 5,
                'required' => true,
                'attr' => [
                    'class' => 'rating-widget',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La note est obligatoire.']),
                    new Assert\Range([
                        'min' => -5,
                        'max' => 5,
                        'notInRangeMessage' => 'La note doit être comprise entre {{ min }} et {{ max }}.',
                    ]),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Votre commentaire',
                'required' => true,
                'attr' => [
                    'class' => 'comment-area',
                    'maxlength' => 1000,
                    'placeholder' => 'Partagez votre expérience en détail (minimum 10 caractères)...',
                    'rows' => 5,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le commentaire est obligatoire.']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 1000,
                        'minMessage' => 'Le commentaire doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'review_form',
        ]);
    }
}
