<?php
declare(strict_types=1);

namespace Nalgoo\App\Error;

use Monolog\Utils;
use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

class ErrorRenderer implements ErrorRendererInterface
{
	private const string FORMAT = '%message% on %file%(%line%)';

	public function __invoke(Throwable $exception, bool $displayErrorDetails): string
	{
		$message = $displayErrorDetails && $exception->getMessage()
			? Utils::getClass($exception) . ': ' . $exception->getMessage()
			: Utils::getClass($exception);

		$parts = [
			'message' => $message,
			'file' => $exception->getFile(),
			'line' => (string) $exception->getLine(),
		];

		$error = self::FORMAT;
		foreach ($parts as $part => $value) {
			$error = str_replace('%'.$part.'%', $value, $error);
		}

		return $displayErrorDetails
		    ? implode("\n", [$error, '##### Stacktrace:', ...$this->getStacktrace($exception), '##### end of stacktrace', ''])
			: $error;
	}

	private function getStacktrace(Throwable $e): array
	{
		return array_map(
			fn($trace, $idx) => sprintf(
				'  %3d %s(%d): %s%s%s()',
				$idx + 1,
				$trace['file'] ?? '[unknown file]',
				$trace['line'] ?? '??',
				$trace['class'] ?? '',
				$trace['type'] ?? '',
				$trace['function'] ?? '[unknown function]',
			),
			$e->getTrace(),
			array_keys($e->getTrace()),
		);
	}
}
