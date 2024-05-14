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
        $leadsData = $request->request->all();
        if ($leadsData == null) {
            throw new BadRequestHttpException('Пусто');
        }

        $this->amoCRMProcessHooksService->processLead($leadsData);

        return $this->json(self::DEFAULT_RESPONSE);
    }

    #[Route(path: '/amo-crm/contacts', methods: [Request::METHOD_POST])]
    public function contactsHook(Request $request): JsonResponse
    {
        $contactsData = $request->request->all();
        if ($contactsData == null) {
            throw new BadRequestHttpException('Пусто');
        }

        $this->amoCRMProcessHooksService->processContact($contactsData);

        return $this->json(self::DEFAULT_RESPONSE);
    }
}
