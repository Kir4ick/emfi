<?php

namespace App\Adapter;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\AmoCRMApiClientFactory;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Collections\EventsCollections;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Filters\EventsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\AccountModel;
use AmoCRM\Models\NoteModel;
use AmoCRM\Models\NoteType\ServiceMessageNote;
use AmoCRM\Models\UserModel;
use App\Adapter\Data\Input\AddNoteInput;
use App\Adapter\Data\Input\GetHistoryInput;
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
        OAuthConfig                      $config,
        OAuthService                     $authService,
        private readonly LoggerInterface $logger,
        private readonly string          $token,
        private readonly string          $domain
    ) {
        # Создание клиента для коннекта с AMO CRM
        $apiClientFactory = new AmoCRMApiClientFactory($config, $authService);

        $this->client = $apiClientFactory->make();
        $longLivenToken = new LongLivedAccessToken($this->token);

        $this->client->setAccessToken($longLivenToken)
            ->setAccountBaseDomain($domain);
    }

    /**
     * Отправление запроса на создание примечания
     *
     * @param AddNoteInput $input
     *
     * @return AddNoteOutput
     */
    public function addNote(AddNoteInput $input): AddNoteOutput
    {
        try {
            $notes = $this->client->notes($input->getEntityType());

            $notesCollection = new NotesCollection();

            $serviceMessageNote = new ServiceMessageNote();
            $serviceMessageNote
                ->setId(1)
                ->setCreatedBy($input->getCreatorID())
                ->setText($input->getNoteText())
                ->setEntityId($input->getEntityID())
                ->setService('Testovoe Zadanie')
                ->setAccountId($input->getCreatorAccountID());

            $notesCollection->add($serviceMessageNote);

            $notes->add($notesCollection);

            return new AddNoteOutput(true);
        } catch (AmoCRMApiException $exception) {
            $message = '[send_notes] ERROR: ' . $exception->getMessage() . $exception->getErrorCode();
            $this->logger->log(LogLevel::ERROR, $message);

            return new AddNoteOutput(false);
        }
    }

    /**
     * Получение пользователя по id
     *
     * @param int $userID
     *
     * @return UserModel|null
     */
    public function getUserByID(int $userID): ?UserModel
    {
        try {
            return $this->client->users()->getOne($userID);
        } catch (Throwable $exception) {
            $message = '[get_account] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return null;
        }
    }

    /**
     * Получение модели текущего пользователя
     *
     * @return AccountModel|null
     */
    public function getCurrentAccount(): ?AccountModel
    {
        try {
            return $this->client->account()->getCurrent();
        } catch (Throwable $exception) {
            $message = '[get_account] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return null;
        }
    }

    /**
     * Получение истории обновления полей
     *
     * @param string $entityType
     *
     * @return EventsCollections|null
     */
    public function getUpdateHistory(string $entityType, int $updatedAt): ?EventsCollections
    {
        try {
            $filter = new EventsFilter();

            $filter->setCreatedAt([
                'from' => $updatedAt - 1000,
                'to' => time() + 2 * 60 * 1000
            ]);
            $filter->setEntity([$entityType]);
            #$filter->setEntityIds([2415047]); // ОНО НЕ РАБОТАЕТ ПОЧЕМУ ТО
            $filter->setTypes([
                'name_field_changed',
                'custom_field_value_changed',
            ]);

            return $this->client->events()->get($filter);
        } catch (AmoCRMApiException $exception) {
            $message = '[get_history] ERROR: ' . $exception->getMessage() . $exception->getTraceAsString();
            $this->logger->log(LogLevel::ERROR, $message);

            return null;
        }
    }

}
