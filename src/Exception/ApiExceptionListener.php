<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiExceptionListener
{

    private array $exceptionCodes = [
        NotFoundHttpException::class => Response::HTTP_NOT_FOUND,
        BadRequestHttpException::class => Response::HTTP_BAD_REQUEST
    ];

    public function __invoke(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();

        $code = $this->exceptionCodes[$throwable::class] ?? Response::HTTP_INTERNAL_SERVER_ERROR;

        $response = new JsonResponse([
            'message' => $throwable->getMessage()
        ], $code);

        $event->setResponse($response);
    }

}
