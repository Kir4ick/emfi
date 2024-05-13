<?php

namespace App\Adapter;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\AmoCRMApiClientFactory;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\NoteModel;
use AmoCRM\Models\NoteType\ServiceMessageNote;
use AmoCRM\Models\UserModel;
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
        private readonly LoggerInterface $logger,
        private string $token
    ) {
        # Создание клиента для коннекта с AMO CRM
        $apiClientFactory = new AmoCRMApiClientFactory($config, $authService);

        $this->client = $apiClientFactory->make();
        $longLivenToken = new LongLivedAccessToken($this->token);

        $this->client->setAccessToken($longLivenToken)
            ->setAccountBaseDomain('kir4ick.amocrm.ru');
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

            $notesCollection = new NotesCollection();

            $serviceMessageNote = new ServiceMessageNote();
            $serviceMessageNote
                ->setId(rand(1000000, 10000000))
                ->setText($input->getNoteText())
                ->setEntityId($input->getEntityID());

            $notesCollection->add($serviceMessageNote);

            $notes->add($notesCollection);

            return new AddNoteOutput(true);
        } catch (AmoCRMApiException $exception) {
            $message = '[send_notes] ERROR: ' . $exception->getMessage() . $exception->getErrorCode();
            $this->logger->log(LogLevel::ERROR, $message);

            return new AddNoteOutput(false);
        }
    }

    public function getUser(int $accountID): ?UserModel
    {
        try {
            return $this->client->users()->getOne($accountID);
        } catch (Throwable $exception) {
            $message = '[get_account] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return null;
        }
    }
}
