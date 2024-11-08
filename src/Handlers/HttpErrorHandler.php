<?php

declare(strict_types=1);

namespace Nalgoo\App\Handlers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

class HttpErrorHandler extends SlimErrorHandler
{
	/**
	 * @inheritdoc
	 */
	protected function respond(): Response
	{
		$exception = $this->exception;

		if ($exception instanceof HttpException) {
			$statusCode = $exception->getCode();
			$error = [
				'code' => 'ERROR',
				'message' => $exception->getMessage(),
			];

			if ($this->displayErrorDetails) {
				$error['details'] = $exception->getDescription();
			}
		} else {
			$statusCode = 500;
			$error = [
				'code' => 'ERROR',
				'message' => 'An internal error has occurred while processing your request.',
			];

			if ($this->displayErrorDetails) {
				$error['details'] = $exception->getMessage() . ' on ' . $exception->getFile() . ':' . $exception->getLine();
			}
		}

		$payload = ['error' => $error];
		$encodedPayload = json_encode($payload, JSON_PRETTY_PRINT);

		$response = $this->responseFactory->createResponse($statusCode);
		$response->getBody()->write($encodedPayload);

		return $response->withHeader('Content-Type', 'application/json');
	}

	/**
	 * copy & paste, but removed "Tips"
	 */
	protected function writeToErrorLog(): void
	{
		$renderer = $this->callableResolver->resolve($this->logErrorRenderer);
		$error = $renderer($this->exception, $this->logErrorDetails);
		$this->logError($error);
	}
}
