<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\CloudinaryService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private LoginSuccessHandler $successHandler,
        private CloudinaryService $cloudinaryService,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_google_callback';
    }

    public function authenticate(Request $request): Passport
    {
        $client      = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email    = $googleUser->getEmail();
                $avatar   = $googleUser->getAvatar();         
                $userType = $request->getSession()->get('google_requested_role', 'ETUDIANT');

                $user = $this->userRepository->findByEmail($email);

                if (!$user) {
                    $user = new User();
                    $user->setName($googleUser->getName());
                    $user->setEmail($email);
                    $user->setPassword('');
                    $user->setUserType($userType);
                    $user->setStatus('ACTIVE');
                    
                    // ✅ Upload Google avatar to Cloudinary and store the URL
                    if ($avatar && filter_var($avatar, FILTER_VALIDATE_URL)) {
                        try {
                            $cloudinaryUrl = $this->cloudinaryService->uploadFromUrl($avatar, 'profiles');
                            $user->setProfilePicture($cloudinaryUrl);
                        } catch (\Exception $e) {
                            // Fallback: store original URL if Cloudinary fails
                            $user->setProfilePicture($avatar);
                        }
                    }
                    
                    $this->em->persist($user);
                } else {
                    // Update role and avatar on every Google login
                    $user->setUserType($userType);
                    
                    // ✅ Update avatar via Cloudinary
                    if ($avatar && filter_var($avatar, FILTER_VALIDATE_URL)) {
                        try {
                            $cloudinaryUrl = $this->cloudinaryService->uploadFromUrl($avatar, 'profiles');
                            $user->setProfilePicture($cloudinaryUrl);
                        } catch (\Exception $e) {
                            $user->setProfilePicture($avatar);
                        }
                    }
                    
                    if ($user->getStatus() !== 'ACTIVE') {
                        $user->setStatus('ACTIVE');
                    }
                }

                $this->em->flush();
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Connexion Google échouée. Réessayez.');
        return new RedirectResponse($this->router->generate('app_login'));
    }
}