<?php

namespace Nalgoo\App\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
	 public function emit(ResponseInterface $response): void;
}
