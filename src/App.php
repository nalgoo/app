<?php

namespace Nalgoo\App;

use Nalgoo\App\Interfaces\ResponseEmitterInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\ResponseEmitter;

readonly class App
{
	public function __construct(
		private \Slim\App $slimApp,
		private ServerRequestCreatorInterface $requestCreator,
		private ResponseEmitterInterface $responseEmitter,
		private ErrorHandlerInterface $errorHandler,
		private bool $displayErrorDetails = false,
	) {
	}

	public function register(callable $callable): void
	{
		call_user_func($callable, $this->slimApp);
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
