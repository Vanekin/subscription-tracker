<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubscriptionControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testHomePageReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testHomePageContainsBody(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');
    }

    public function testAddPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/add');

        $this->assertResponseRedirects();
    }

    public function testMySubscriptionsRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions');

        $this->assertResponseRedirects();
    }

    public function testShowSubscriptionReturns404WhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1');

        $this->assertResponseStatusCodeSame(404);
    }


    public function testEditSubscriptionReturns404WhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaySubscriptionRejectsGetRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1/pay');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testCancelSubscriptionRejectsGetRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1/cancel');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testDeleteSubscriptionRejectsGetRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1/delete');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testReactivateSubscriptionRejectsGetRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/1/reactivate');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testShowSubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/abc');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditSubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/my-subscriptions/abc/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaySubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/my-subscriptions/abc/pay');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCancelSubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/my-subscriptions/abc/cancel');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteSubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/my-subscriptions/abc/delete');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReactivateSubscriptionRejectsNonNumericId(): void
    {
        $client = static::createClient();
        $client->request('POST', '/my-subscriptions/abc/reactivate');

        $this->assertResponseStatusCodeSame(404);
    }
}
