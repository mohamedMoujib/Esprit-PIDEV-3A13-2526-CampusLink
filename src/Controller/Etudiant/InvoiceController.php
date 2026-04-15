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
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepo
    ): Response {
        $user = $this->getUser();
        $date = $request->query->get('date');

        $qb = $invoiceRepo->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user);

        if ($date) {
            $start = new \DateTime($date . ' 00:00:00');
            $end = new \DateTime($date . ' 23:59:59');

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
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($invoice);
        $em->flush();

        $this->addFlash('success', 'Facture supprimée avec succès.');

        return $this->redirectToRoute('invoice_index');
    }

    #[Route('/invoices/{id}', name: 'invoice_preview')]
    public function preview(Invoice $invoice): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('etudiant/invoice_preview.html.twig', [
            'invoice' => $invoice
        ]);
    }
}