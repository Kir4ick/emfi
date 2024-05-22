<?php

namespace App\Adapter;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\AmoCRMApiClientFactory;
use AmoCRM\Collections\BaseApiCollection;
use AmoCRM\Models\AccountModel;
use App\Adapter\Data\Input\AddNoteInput;
use App\Adapter\Data\Output\AddNoteOutput;
use App\AmoCRM\OAuthConfig;
use App\AmoCRM\OAuthService;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class AmoCRMAdapter
{

    private AmoCRMApiClient $client;

    public function __construct(
        OAuthConfig $config,
        OAuthService $authService,
        private readonly LoggerInterface $logger
    ) {
        # Создание клиента для коннекта с AMO CRM
        $apiClientFactory = new AmoCRMApiClientFactory($config, $authService);

        $this->client = $apiClientFactory->make();
    }

    /**
     * Отправление запроса на создание примечания
     *
     * @param AddNoteInput $input
     * @return AddNoteOutput
     */
    public function addNote(AddNoteInput $input): AddNoteOutput
    {
        try {
            $notes = $this->client->notes($input->getEntityType());
            $notes->add(BaseApiCollection::make([
                ['id' => $input->getEntityID(), 'params' => ['text' => $input->getNoteText()]]
            ]));

            return new AddNoteOutput(true);
        } catch (Throwable $exception) {
            $message = '[send_notes] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return new AddNoteOutput(false);
        }

    }

    public function getAccount(int $accountID): ?AccountModel
    {
        try {
            return $this->client->account()->getOne($accountID);
        } catch (Throwable $exception) {
            $message = '[get_account] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return null;
        }
    }
}
