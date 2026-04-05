<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminReviewsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/reviews', name: 'admin_reviews')]
    public function index(): Response
    {
        $reviews = $this->em->getRepository(Review::class)->createQueryBuilder('r')
            ->leftJoin('r.student', 'student')->addSelect('student')
            ->leftJoin('r.prestataire', 'prestataire')->addSelect('prestataire')
            ->leftJoin('r.reservation', 'reservation')->addSelect('reservation')
            ->orderBy('r.id', 'DESC')
            ->getQuery()->getResult();

        return $this->render('admin/pages/reviews.html.twig', [
            'reviews' => $reviews,
        ]);
    }
}
