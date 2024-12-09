<?php

namespace Nalgoo\App;

use Nalgoo\App\Interfaces\ResponseEmitterInterface;
use Nalgoo\App\Middleware\ReverseProxyMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\Middleware\ErrorMiddleware;

class App
{
	public function __construct(
		readonly private \Slim\App $slimApp,
		readonly private ServerRequestCreatorInterface $requestCreator,
		readonly private ResponseEmitterInterface $responseEmitter,
		readonly private ErrorHandlerInterface $errorHandler,
		private bool $displayErrorDetails = false,
	) {
	}

	public function setDisplayErrorDetails(bool $displayErrorDetails): void
	{
		$this->displayErrorDetails = $displayErrorDetails;
	}

	public function register(callable $callable): void
	{
		call_user_func($callable, $this->slimApp);
	}

	/**
	 * This should be called *AFTER* adding custom middleware, to be on top of the stack
	 */
	public function registerCoreMiddleware(): void
	{
		$this->slimApp->add(new ReverseProxyMiddleware());
		$this->slimApp->addBodyParsingMiddleware();
		$this->slimApp->addRoutingMiddleware();
		$this->slimApp->add($this->slimApp->getContainer()->get(ErrorMiddleware::class));
	}

	public function run(): void
	{
		$request = $this->requestCreator->createServerRequestFromGlobals();

		register_shutdown_function([$this, 'onShutdown'], $request);

		$response = $this->slimApp->handle($request);
		$this->responseEmitter->emit($response);
	}

	public function onShutdown(ServerRequestInterface $request): void
	{
		$error = error_get_last();

		if ($error) {
			$exception = new \ErrorException($error['message'], $error['type'], 1, $error['file'], $error['line']);
			$response = $this->errorHandler->__invoke($request, $exception, $this->displayErrorDetails, false, false);
			$this->responseEmitter->emit($response);
		}
	}
}
