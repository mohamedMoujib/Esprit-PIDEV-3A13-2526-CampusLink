<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/messages')]
class MessageController extends AbstractController
{
    #[Route('/', name: 'messages')]
    public function index(
        MessageRepository $repo,
        UserRepository $userRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        /** @var User $user */
        $user = $this->getUser();

        // 🔥 MAJ messages programmés
        $this->updateScheduledMessages($em);

        // 🔥 récupérer messages visibles
        $allMessages = $repo->createQueryBuilder('m')
            ->where('(m.sender = :user OR m.receiver = :user)')
            ->andWhere('(m.scheduledAt IS NULL OR m.scheduledAt <= :now)')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('m.timestamp', 'DESC')
            ->getQuery()
            ->getResult();

        // 🔥 conversations
        $conversations = [];

        foreach ($allMessages as $msg) {

            if (!$msg->getSender() || !$msg->getReceiver()) continue;

            $other = ($msg->getSender()->getId() === $user->getId())
                ? $msg->getReceiver()
                : $msg->getSender();

            if ($other && $other->getId() !== $user->getId()) {
                $conversations[$other->getId()] = $other;
            }
        }

        $selectedId = $request->query->get('user');
        $messages = [];
        $selectedUser = null;

        if ($selectedId) {

            $selectedUser = $userRepo->find($selectedId);

            if ($selectedUser) {

                $messages = $repo->createQueryBuilder('m')
                    ->where('(m.sender = :me AND m.receiver = :other) OR (m.sender = :other AND m.receiver = :me)')
                    ->andWhere('(m.scheduledAt IS NULL OR m.scheduledAt <= :now)')
                    ->setParameter('me', $user)
                    ->setParameter('other', $selectedUser)
                    ->setParameter('now', new \DateTime())
                    ->orderBy('m.timestamp', 'ASC')
                    ->getQuery()
                    ->getResult();

                // 🔥 marquer comme lus
                foreach ($messages as $msg) {
                    if ($msg->getReceiver() === $user && !$msg->isRead()) {
                        $msg->setIsRead(true);
                    }
                }

                $em->flush();
            }
        }

        return $this->render('message/index.html.twig', [
            'conversations' => $conversations,
            'messages' => $messages,
            'selectedUser' => $selectedUser
        ]);
    }

    #[Route('/send/{id}', name: 'message_send', methods: ['POST'])]
    public function send(User $receiver, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $content = trim($request->request->get('content'));
        $scheduled = $request->request->get('scheduledAt');

        if ($content) {

            $msg = new Message();
            $msg->setSender($user);
            $msg->setReceiver($receiver);
            $msg->setContent($content);

            if ($scheduled) {
                $msg->setScheduledAt(new \DateTime($scheduled));
                $msg->setIsSent(false);
            } else {
                $msg->setIsSent(true);
            }

            $em->persist($msg);
            $em->flush();
        }

        return $this->redirectToRoute('messages', ['user' => $receiver->getId()]);
    }

    #[Route('/send-audio/{id}', name: 'message_audio', methods: ['POST'])]
    public function sendAudio(User $receiver, Request $request, EntityManagerInterface $em): Response
    {
        $file = $request->files->get('audio');

        if ($file) {
            $filename = uniqid().'.webm';
            $file->move('uploads/audio', $filename);

            $msg = new Message();
            $msg->setSender($this->getUser());
            $msg->setReceiver($receiver);
            $msg->setContent('[AUDIO]'.$filename);
            $msg->setIsSent(true);

            $em->persist($msg);
            $em->flush();
        }

        return new Response('ok');
    }

    private function updateScheduledMessages(EntityManagerInterface $em)
    {
        $messages = $em->getRepository(Message::class)->createQueryBuilder('m')
            ->where('m.scheduledAt <= :now')
            ->andWhere('m.isSent = false')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        foreach ($messages as $msg) {
            $msg->setIsSent(true);
        }

        $em->flush();
    }
}