<?php

declare(strict_types=1);

namespace JuheItSolutions\JuheConsentBundle\Tests\EventListener;

use JuheItSolutions\JuheConsentBundle\EventListener\InjectConsentListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ScriptNonceTest extends TestCase
{
    public function testPreservesEveryPolicyWhenSeveralHeadersArePresent(): void
    {
        $reflection = new \ReflectionClass(InjectConsentListener::class);
        $response = new Response('', 200, ['Content-Security-Policy' => [
            "script-src 'self'; frame-src 'none'",
            "script-src 'nonce-second'; connect-src 'none'",
        ]]);

        $nonce = $reflection->getMethod('scriptNonce')->invoke($reflection->newInstanceWithoutConstructor(), $response);
        $policies = $response->headers->all('Content-Security-Policy');

        $this->assertCount(2, $policies);
        $this->assertStringContainsString("frame-src 'none'", $policies[0]);
        $this->assertStringContainsString("connect-src 'none'", $policies[1]);
        foreach ($policies as $policy) {
            $this->assertStringContainsString("'nonce-".$nonce."'", $policy);
        }
    }

    public function testPreservesPoliciesCoalescedIntoOneHeader(): void
    {
        $reflection = new \ReflectionClass(InjectConsentListener::class);
        $response = new Response('', 200, ['Content-Security-Policy' => "script-src 'self', script-src 'nonce-second'; connect-src 'none'"]);

        $nonce = $reflection->getMethod('scriptNonce')->invoke($reflection->newInstanceWithoutConstructor(), $response);
        $policies = $response->headers->all('Content-Security-Policy');

        $this->assertCount(2, $policies);
        foreach ($policies as $policy) {
            $this->assertStringContainsString("'nonce-".$nonce."'", $policy);
        }
        $this->assertStringContainsString("connect-src 'none'", $policies[1]);
    }

    public function testDoesNotRelaxOtherDefaultResourceTypes(): void
    {
        $reflection = new \ReflectionClass(InjectConsentListener::class);
        $response = new Response('', 200, ['Content-Security-Policy' => "default-src 'none'"]);

        $nonce = $reflection->getMethod('scriptNonce')->invoke($reflection->newInstanceWithoutConstructor(), $response);

        $this->assertSame("default-src 'none'; script-src 'none' 'nonce-".$nonce."'", $response->headers->get('Content-Security-Policy'));
    }

    public function testOneScriptNonceWorksInEnforcedAndReportOnlyPolicies(): void
    {
        $reflection = new \ReflectionClass(InjectConsentListener::class);
        $listener = $reflection->newInstanceWithoutConstructor();
        $response = new Response('', 200, [
            'Content-Security-Policy' => "script-src 'self' 'nonce-first'",
            'Content-Security-Policy-Report-Only' => "script-src-elem 'nonce-second'",
        ]);

        $nonce = $reflection->getMethod('scriptNonce')->invoke($listener, $response);

        $this->assertSame('first', $nonce);
        $this->assertStringContainsString("'nonce-first'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("'nonce-first'", $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString("'nonce-second'", $response->headers->get('Content-Security-Policy-Report-Only'));
    }
}
