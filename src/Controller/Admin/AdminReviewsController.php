<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminReviewsController extends AbstractController
{
    #[Route('/reviews', name: 'admin_reviews')]
    public function index(): Response
    {
        return $this->render('admin/pages/reviews.html.twig');
    }
}