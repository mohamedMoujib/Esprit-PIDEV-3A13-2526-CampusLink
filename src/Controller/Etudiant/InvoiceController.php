<?php

namespace App\Controller\Etudiant;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceController extends AbstractController
{
    #[Route('/invoices', name: 'invoice_index')]
    public function index(Request $request, InvoiceRepository $invoiceRepo): Response
    {
        $user = $this->getUser();
        $date = $request->query->get('date');

        $qb = $invoiceRepo->createQueryBuilder('i')
            ->innerJoin('i.payment', 'p')
            ->innerJoin('p.reservation', 'r')
            ->andWhere('r.user = :userId')
            ->setParameter('userId', $user->getId());

        if ($date) {
            $start = new \DateTime($date . ' 00:00:00');
            $end   = new \DateTime($date . ' 23:59:59');
            $qb->andWhere('i.issueDate BETWEEN :start AND :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        }

        $qb->orderBy('i.issueDate', 'DESC');

        $invoices = $qb->getQuery()->getResult();

        return $this->render('etudiant/invoice.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/delete/{id}', name: 'invoice_delete', methods: ['POST'])]
    public function delete(Invoice $invoice, EntityManagerInterface $em): Response
    {
        $em->remove($invoice);
        $em->flush();

        $this->addFlash('success', 'Facture supprimée avec succès.');
        return $this->redirectToRoute('invoice_index');
    }

    #[Route('/invoices/{id}', name: 'invoice_preview', requirements: ['id' => '\d+'])]
    public function preview(Invoice $invoice): Response
    {
        return $this->render('etudiant/invoice_preview.html.twig', [
            'invoice' => $invoice,
        ]);
    }
}