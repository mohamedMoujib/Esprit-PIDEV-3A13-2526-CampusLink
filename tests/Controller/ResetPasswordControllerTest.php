<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class ResetPasswordControllerTest extends WebTestCase
{
    /**
     * HELPER — initialises a session by hitting /forgot-password (always renders),
     * injects the given key/value pairs, saves, and returns the ready client.
     */
    private function createClientWithSession(array $sessionData): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();

        // Boot on a page that never redirects so we get a real session object
        $client->request('GET', '/forgot-password');

        $session = $client->getRequest()->getSession();
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }
        $session->save();

        // Carry the session cookie into all subsequent requests
        $client->getCookieJar()->set(
            new Cookie($session->getName(), $session->getId())
        );

        return $client;
    }

    // =========================================================
    // STEP 1 - /forgot-password
    // =========================================================

    public function testForgotPasswordPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/forgot-password');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testForgotPasswordWithUnknownEmailStillRedirects(): void
    {
        $client = static::createClient();
        $client->request('POST', '/forgot-password', ['email' => 'nobody@unknown.com']);
        $this->assertResponseRedirects('/verify-code');
    }

    public function testForgotPasswordWithKnownEmailRedirects(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setName('Test User');
        $user->setEmail('reset@test.com');
        $user->setPassword(password_hash('Secret1234', PASSWORD_BCRYPT));
        $user->setUserType('ETUDIANT');
        $user->setStatus('ACTIVE');
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/forgot-password', ['email' => 'reset@test.com']);
        $this->assertResponseRedirects('/verify-code');

        $em->remove($user);
        $em->flush();
    }

    // =========================================================
    // STEP 2 - /verify-code
    // =========================================================

    public function testVerifyCodeRedirectsIfNoSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify-code');
        $this->assertResponseRedirects('/forgot-password');
    }

    public function testVerifyCodePageLoadsWithSession(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'        => 'reset@test.com',
            'reset_code'         => '482910',
            'reset_code_expires' => time() + 900,
        ]);

        $client->request('GET', '/verify-code');
        $this->assertResponseIsSuccessful();
    }

    public function testVerifyCodeWithExpiredCode(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'        => 'reset@test.com',
            'reset_code'         => '482910',
            'reset_code_expires' => time() - 1,
        ]);

        $client->request('POST', '/verify-code', ['code' => '482910']);
        $this->assertResponseRedirects('/forgot-password');
    }

    public function testVerifyCodeWithWrongCode(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'        => 'reset@test.com',
            'reset_code'         => '482910',
            'reset_code_expires' => time() + 900,
        ]);

        $client->request('POST', '/verify-code', ['code' => '000000']);
        $this->assertResponseRedirects('/verify-code');
    }

    public function testVerifyCodeWithCorrectCode(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'        => 'reset@test.com',
            'reset_code'         => '482910',
            'reset_code_expires' => time() + 900,
        ]);

        $client->request('POST', '/verify-code', ['code' => '482910']);
        $this->assertResponseRedirects('/reset-password');
    }

    // =========================================================
    // STEP 3 - /reset-password
    // =========================================================

    public function testResetPasswordRedirectsIfNotVerified(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password');
        $this->assertResponseRedirects('/forgot-password');
    }

    public function testResetPasswordWithMismatchedPasswords(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'    => 'reset@test.com',
            'reset_verified' => true,
        ]);

        $client->request('POST', '/reset-password', [
            'password'        => 'Secret1234',
            'confirmPassword' => 'Different99',
        ]);
        $this->assertResponseRedirects('/reset-password');
    }

    public function testResetPasswordWithWeakPassword(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'    => 'reset@test.com',
            'reset_verified' => true,
        ]);

        $client->request('POST', '/reset-password', [
            'password'        => 'weakpass',
            'confirmPassword' => 'weakpass',
        ]);
        $this->assertResponseRedirects('/reset-password');
    }

    public function testResetPasswordWithPasswordMissingUppercase(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'    => 'reset@test.com',
            'reset_verified' => true,
        ]);

        $client->request('POST', '/reset-password', [
            'password'        => 'secret1234',
            'confirmPassword' => 'secret1234',
        ]);
        $this->assertResponseRedirects('/reset-password');
    }

    public function testResetPasswordWithPasswordMissingNumber(): void
    {
        $client = $this->createClientWithSession([
            'reset_email'    => 'reset@test.com',
            'reset_verified' => true,
        ]);

        $client->request('POST', '/reset-password', [
            'password'        => 'SecretPass',
            'confirmPassword' => 'SecretPass',
        ]);
        $this->assertResponseRedirects('/reset-password');
    }
}