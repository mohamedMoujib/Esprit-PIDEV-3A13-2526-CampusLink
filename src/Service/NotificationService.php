<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $repo,
        private readonly MailerInterface $mailer,
    ) {}

    public function notifyInApp(User $user, string $title, string $message): void
    {
        $notif = (new Notification())
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message);

        $this->em->persist($notif);
        $this->em->flush();
    }

    public function getForUser(User $user): array
    {
        return array_map(
            fn(Notification $n) => $n->toArray(),
            $this->repo->findByUser($user)
        );
    }

    public function getUnreadCount(User $user): int
    {
        return $this->repo->countUnread($user);
    }

    public function markAsRead(User $user, int $id): bool
    {
        $notif = $this->repo->findOneOwnedByUser($id, $user);
        if (!$notif) {
            return false;
        }

        $notif->setStatus('READ');
        $this->em->flush();

        return true;
    }

    public function clearForUser(User $user): void
    {
        $this->em->createQuery(
            'UPDATE App\\Entity\\Notification n SET n.status = :read WHERE n.user = :u'
        )
        ->setParameter('read', 'READ')
        ->setParameter('u', $user)
        ->execute();
    }

    public function sendEmail(string $to, string $subject, string $body): void
    {
        $email = (new Email())
            ->from($_ENV['MAILER_FROM'] ?? 'noreply@campuslink.tn')
            ->to($to)
            ->subject($subject)
            ->html($this->buildTemplate($subject, $body));

        $this->mailer->send($email);
    }

    private function buildTemplate(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:'Segoe UI',sans-serif;background:#f4f4f7}
.container{max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden}
.header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:30px;text-align:center}
.content{padding:40px 30px;color:#333;line-height:1.8}
.highlight{background:#f0f4ff;padding:20px;border-radius:8px;border-left:4px solid #667eea}
.footer{background:#f8f9fa;padding:20px;text-align:center;color:#6c757d;font-size:13px}
</style></head><body>
<div class="container">
  <div class="header"><h1>🎓 CampusLink</h1></div>
  <div class="content"><h2>$title</h2><div class="highlight">$content</div></div>
  <div class="footer"><p>CampusLink – Plateforme étudiante</p></div>
</div></body></html>
HTML;
    }
}
