<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Core\User\UserInterface;
use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GoogleAuthenticator extends OAuth2Authenticator
{
    private $clientRegistry;
    private $em;
    private $router;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $em, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $client = $this->clientRegistry->getClient('google');
            $accessToken = $client->getAccessToken();
            $googleUser = $client->fetchUserFromToken($accessToken);

            /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
            $email = $googleUser->getEmail();
            $name = $googleUser->getName();

            // Check if we're adding a Gmail account
            $isAddingAccount = $request->getSession()->get('adding_gmail_account', false);

            return new SelfValidatingPassport(
                new UserBadge($email, function($userIdentifier) use ($email, $name, $accessToken, $isAddingAccount, $request) {
                    if ($isAddingAccount) {
                        // We're adding a Gmail account to the current user
                        // Get the current user from the session or token
                        $currentUser = $this->getCurrentUserFromSession($request);
                        if (!$currentUser) {
                            throw new \Exception('No authenticated user found');
                        }

                        // Check if this Gmail account is already connected to the current user
                        $existingGmailAccount = $this->em->getRepository(\App\Entity\GmailAccount::class)
                            ->findOneBy(['email' => $email, 'owner' => $currentUser]);
                        
                        if ($existingGmailAccount) {
                            // Update existing Gmail account token
                            $existingGmailAccount->setAccessToken(json_encode($accessToken));
                            $existingGmailAccount->setName($name);
                        } else {
                            // Create new Gmail account connection
                            $gmailAccount = new \App\Entity\GmailAccount();
                            $gmailAccount->setEmail($email);
                            $gmailAccount->setName($name);
                            $gmailAccount->setAccessToken(json_encode($accessToken));
                            $gmailAccount->setOwner($currentUser);
                            $this->em->persist($gmailAccount);
                        }

                        $this->em->flush();
                        
                        // Clear the session flags
                        $request->getSession()->remove('adding_gmail_account');
                        $request->getSession()->remove('current_user_email');
                        
                        // Return the current user (don't change authentication)
                        return $currentUser;
                    } else {
                        // Normal authentication flow
                        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                        if (!$user) {
                            $user = new User();
                            $user->setEmail($email);
                            $user->setRoles(['ROLE_USER']);
                            $user->setName($name);
                            $this->em->persist($user);
                        }

                        // Check if this Gmail account is already connected
                        $existingGmailAccount = $this->em->getRepository(\App\Entity\GmailAccount::class)
                            ->findOneBy(['email' => $email, 'owner' => $user]);
                        
                        if (!$existingGmailAccount) {
                            // Create new Gmail account connection
                            $gmailAccount = new \App\Entity\GmailAccount();
                            $gmailAccount->setEmail($email);
                            $gmailAccount->setName($name);
                            $gmailAccount->setAccessToken(json_encode($accessToken));
                            $gmailAccount->setOwner($user);
                            $this->em->persist($gmailAccount);
                        } else {
                            // Update existing Gmail account token
                            $existingGmailAccount->setAccessToken(json_encode($accessToken));
                            $existingGmailAccount->setName($name);
                        }

                        $this->em->flush();
                        return $user;
                    }
                })
            );
        } catch (InvalidStateException $e) {
            throw new NotFoundHttpException('Invalid OAuth state', $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        return new RedirectResponse($this->router->generate('failure'));
    }

    private function getCurrentUserFromSession(Request $request): ?User
    {
        // Get the current user's email from the session
        $session = $request->getSession();
        $currentUserEmail = $session->get('current_user_email');
        
        if ($currentUserEmail) {
            return $this->em->getRepository(User::class)->findOneBy(['email' => $currentUserEmail]);
        }
        
        return null;
    }
} 