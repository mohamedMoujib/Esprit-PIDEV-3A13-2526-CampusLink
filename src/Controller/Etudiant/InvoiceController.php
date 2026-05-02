<?php

namespace App\Controller\Etudiant;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class InvoiceController extends AbstractController
{
    #[Route('/invoices', name: 'invoice_index')]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepo,
        ChartBuilderInterface $chartBuilder
    ): Response {
        $user = $this->getUser();
        $date = $request->query->get('date');

        // ── Filtered invoices for the TABLE ──────────────────────────
        $qb = $invoiceRepo->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user);

        if ($date) {
            $start = new \DateTime($date . ' 00:00:00');
            $end   = new \DateTime($date . ' 23:59:59');
            $qb->andWhere('i.issueDate BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $qb->orderBy('i.issueDate', 'DESC');
        $invoices = $qb->getQuery()->getResult();

        // ── ALL invoices for the CHARTS (ignores date filter) ─────────
        $allInvoices = $invoiceRepo->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.issueDate', 'ASC')
            ->getQuery()
            ->getResult();

        // ── Build chart data ──────────────────────────────────────────
        $statsByDate   = [];
        $statsByMethod = [];
        $totalAmount   = 0;

        foreach ($allInvoices as $invoice) {
            $key = $invoice->getIssueDate()->format('d/m/Y');
            $statsByDate[$key] = ($statsByDate[$key] ?? 0) + 1;

            $method = $invoice->getPayment()?->getMethod() ?? 'Non précisé';
            $statsByMethod[$method] = ($statsByMethod[$method] ?? 0) + 1;
        }

        // Total amount only from filtered invoices
        foreach ($invoices as $invoice) {
            $totalAmount += (float) ($invoice->getPayment()?->getAmount() ?? 0);
        }

        // ── Bar chart: invoices per day ───────────────────────────────
        $barChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $barChart->setData([
            'labels' => array_keys($statsByDate) ?: ['Aucune donnée'],
            'datasets' => [[
                'label'           => 'Factures',
                'data'            => array_values($statsByDate) ?: [0],
                'backgroundColor' => 'rgba(91,84,232,0.80)',
                'borderColor'     => '#5b54e8',
                'borderWidth'     => 2,
                'borderRadius'    => 8,
                'borderSkipped'   => false,
            ]],
        ]);
        $barChart->setOptions([
            'responsive' => true,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'backgroundColor' => '#1a1a2e',
                    'padding'         => 10,
                    'cornerRadius'    => 8,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid'  => ['display' => false],
                    'ticks' => ['color' => '#6b7280'],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid'        => ['color' => '#f0effc'],
                    'ticks'       => ['precision' => 0, 'color' => '#6b7280'],
                ],
            ],
        ]);

        // ── Donut chart: PHYSICAL vs VIRTUAL ─────────────────────────
        $methodLabels = [];
        foreach (array_keys($statsByMethod) as $key) {
            $methodLabels[] = match($key) {
                'PHYSICAL'    => '🤝 Physique',
                'VIRTUAL'     => '💻 Virtuelle',
                default       => $key,
            };
        }

        $donutChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $donutChart->setData([
            'labels' => $methodLabels ?: ['Aucune donnée'],
            'datasets' => [[
                'data'            => array_values($statsByMethod) ?: [1],
                'backgroundColor' => ['#5b54e8', '#f59e0b', '#e24b4a', '#9b8ff4'],
                'borderWidth'     => 0,
                'hoverOffset'     => 10,
            ]],
        ]);
        $donutChart->setOptions([
            'responsive' => true,
            'cutout'     => '70%',
            'plugins'    => [
                'legend' => [
                    'position' => 'bottom',
                    'labels'   => [
                        'color'           => '#1a1a2e',
                        'padding'         => 16,
                        'usePointStyle'   => true,
                        'pointStyleWidth' => 10,
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => '#1a1a2e',
                    'padding'         => 10,
                    'cornerRadius'    => 8,
                ],
            ],
        ]);

        return $this->render('etudiant/invoice.html.twig', [
            'invoices'    => $invoices,
            'totalAmount' => $totalAmount,
            'barChart'    => $barChart,
            'donutChart'  => $donutChart,
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
            'invoice' => $invoice,
        ]);
    }
}