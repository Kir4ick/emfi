<?php

namespace App\Controller;

use App\Service\AmoCRMProcessHooksService;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * КОнтроллер по интеграции с АМО CRM
 */
class AmoCRMIntegrationController extends AbstractController
{

    private const DEFAULT_RESPONSE = 'success';

    public function __construct(
        private readonly AmoCRMProcessHooksService $amoCRMProcessHooksService
    ) {}

    #[Route(path: '/amo-crm/leads', methods: [Request::METHOD_POST])]
    public function leadsHook(Request $request): JsonResponse
    {
        $leadsData = $request->getContent();
        if ($leadsData == null) {
            throw new BadRequestHttpException();
        }

        $this->amoCRMProcessHooksService->processLead(json_decode($leadsData, true));

        return $this->json(self::DEFAULT_RESPONSE);
    }

    #[Route(path: '/amo-crm/contacts', methods: [Request::METHOD_POST])]
    public function contactsHook(Request $request): JsonResponse
    {
        return $this->json(self::DEFAULT_RESPONSE);
    }
}
