<?php

declare(strict_types=1);

namespace tests\Libero\PingController;

use Error;
use ErrorException;
use Exception;
use Libero\PingController\PingController;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Debug\BufferingLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use function trigger_error;
use const E_USER_DEPRECATED;
use const E_USER_NOTICE;

final class PingControllerTest extends TestCase
{
    /**
     * @test
     */
    public function it_pings() : void
    {
        $controller = new PingController();

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pong', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertFalse($response->headers->has('Expires'));
    }

    /**
     * @test
     */
    public function it_returns_an_expire_header_to_a_http_1_0_request() : void
    {
        $controller = new PingController();

        $request = new Request();
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');

        $response = $controller($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pong', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    /**
     * @test
     */
    public function it_pings_with_a_check() : void
    {
        $logger = new BufferingLogger();
        $count = 0;
        $check = function () use (&$count) : void {
            $count++;
        };

        $controller = new PingController($check, $logger);

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pong', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertCount(0, $logger->cleanLogs());
        $this->assertSame(1, $count);
    }

    /**
     * @test
     */
    public function it_fails_when_there_is_a_runtime_exception() : void
    {
        $exception = new RuntimeException('Problem');

        $controller = new PingController($this->willThrow($exception));

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame('Service Unavailable', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /**
     * @test
     */
    public function it_logs_a_runtime_exception() : void
    {
        $exception = new RuntimeException('Problem');

        $logger = new BufferingLogger();

        $controller = new PingController($this->willThrow($exception), $logger);

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame('Service Unavailable', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame([[LogLevel::CRITICAL, 'Ping failed', ['exception' => $exception]]], $logger->cleanLogs());
    }

    /**
     * @test
     */
    public function it_fails_when_there_is_an_exception() : void
    {
        $exception = new Exception('Problem');

        $controller = new PingController($this->willThrow($exception));

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /**
     * @test
     */
    public function it_logs_an_exception() : void
    {
        $exception = new Exception('Problem');

        $logger = new BufferingLogger();

        $controller = new PingController($this->willThrow($exception), $logger);

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame([[LogLevel::ERROR, 'Ping failed', ['exception' => $exception]]], $logger->cleanLogs());
    }

    /**
     * @test
     */
    public function it_fails_when_there_is_a_throwable() : void
    {
        $error = new Error('Problem');

        $controller = new PingController($this->willThrow($error));

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /**
     * @test
     */
    public function it_logs_a_throwable() : void
    {
        $error = new Error('Problem');

        $logger = new BufferingLogger();

        $controller = new PingController($this->willThrow($error), $logger);

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame([[LogLevel::CRITICAL, 'Ping failed', ['exception' => $error]]], $logger->cleanLogs());
    }

    /**
     * @test
     */
    public function it_fails_when_there_is_an_error() : void
    {
        $expected = new ErrorException('Problem', 0, E_USER_NOTICE);

        $controller = new PingController($this->willTrigger($expected));

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    /**
     * @test
     */
    public function it_logs_an_error() : void
    {
        $expected = new ErrorException('Problem', 0, E_USER_NOTICE);

        $logger = new BufferingLogger();

        $controller = new PingController($this->willTrigger($expected), $logger);

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertEquals([[LogLevel::ERROR, 'Ping failed', ['exception' => $expected]]], $logger->cleanLogs());
    }

    /**
     * @test
     */
    public function it_ignores_deprecations() : void
    {
        $logger = new BufferingLogger();

        $controller = new PingController(
            function () : void {
                trigger_error('foo', E_USER_DEPRECATED);
                trigger_error('bar', E_USER_DEPRECATED);
            },
            $logger
        );

        $response = $controller(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pong', $response->getContent());
        $this->assertSame('must-revalidate, no-store, private', $response->headers->get('Cache-Control'));
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame([[LogLevel::NOTICE, 'foo', []], [LogLevel::NOTICE, 'bar', []]], $logger->cleanLogs());
    }

    private function willTrigger(ErrorException $error) : callable
    {
        return function () use ($error) : void {
            trigger_error($error->getMessage(), $error->getSeverity());
        };
    }

    private function willThrow(Throwable $throwable) : callable
    {
        return function () use ($throwable) : void {
            throw $throwable;
        };
    }
}
