<?php

namespace App\Service;

use AmoCRM\Collections\EventsCollections;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\EventModel;
use App\Adapter\AmoCRMAdapter;
use App\Adapter\Data\Input\AddNoteInput;
use App\Adapter\Data\Input\GetHistoryInput;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AmoCRMProcessHooksService
{

    private const CREATE = 'add';

    private const UPDATE = 'update';

    public function __construct(
        private readonly AmoCRMAdapter $amoCRMAdapter,
        private readonly LoggerInterface $logger
    )
    {}

    /**
     * Обработка лида
     *
     * @param array $data
     *
     * @return void
     */
    public function processLead(array $data): void
    {
        $entityType = EntityTypesInterface::LEADS;
        [$mappedData, $action] = $this->mapHookData($data, $entityType);

        $history = null;
        if ($action == self::UPDATE) {
            $history = $this->amoCRMAdapter->getUpdateHistory(EntityTypesInterface::LEAD);
        }

        $this->sendNoteForEntities($mappedData, $entityType, $action, $history);
    }

    /**
     * Обработка контакта
     *
     * @param array $contactData
     *
     * @return void
     */
    public function processContact(array $contactData)
    {
        $entityType = EntityTypesInterface::CONTACTS;

        [$mappedData, $action] = $this->mapHookData($contactData, $entityType);

        $history = null;
        if ($action == self::UPDATE) {
            $history = $this->amoCRMAdapter->getUpdateHistory(EntityTypesInterface::CONTACTS);
        }

        $this->sendNoteForEntities($mappedData, $entityType, $action, $history);
    }

    /**
     * Мап приходящих с хука данных
     *
     * @param array $data - Данные, которые пришли
     * @param string $key - ключ сущности
     *
     * @return array
     */
    private function mapHookData(array $data, string $key): array
    {
        $hookData = $data[$key] ?? null;
        if ($hookData == null) {
            throw new BadRequestHttpException('Не передано данных');
        }

        # Вытаскиваем событие
        $action = array_keys($hookData)[0] ?? null;
        if ($action == null) {
            throw new BadRequestHttpException('Не передано событие');
        }

        $hookData = $hookData[$action];

        # Мапим приходящие данные
        $mappedData = array_map(function (array $data) {
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'created_user_id' => $data['created_user_id'],
                'modified_user_id' => $data['modified_user_id'] ?? 0,
                'date_create' => date('Y-m-d H:i:s', $data['date_create']),
                'date_updated' => date('Y-m-d H:i:s', $data['last_modified']),
                'responsible_user_id' => $data['responsible_user_id'] ?? 0,
            ];
        }, $hookData);

        return [$mappedData, $action];
    }

    /**
     * Создание сообщение на создание сущности
     *
     * @param int $responsibleUserID
     * @param string $name
     * @param string $dateTime
     *
     * @return string|null
     */
    private function generateCreateNoteMessage(
        int $responsibleUserID,
        string $name,
        string $dateTime
    ): string {
        $userModel = $this->amoCRMAdapter->getUserByID($responsibleUserID);
        if ($userModel == null) {
            throw new BadRequestHttpException('Не удалось найти аккаунт');
        }

        return sprintf(
            'Добавлен %s, ответственный пользователь: %s, время создания: %s',
            $name,
            $userModel->getName(),
            $dateTime
        );
    }

    /**
     * Создание сообщение об обновления сущности
     *
     * @param array $updatedFields
     * @param string $name
     * @param string $dateTime
     *
     * @return string
     */
    private function generateUpdateNoteMessage(array $updatedFields, string $name, string $dateTime): string
    {
        $updatedFieldsData = [];
        foreach ($updatedFields as $fieldList) {
            foreach ($fieldList as $fieldName => $updatedField) {
                $updatedFieldsData[] = $fieldName . ' обновлено на: ' . $updatedField;
            }
        }

        $updatedFieldsData = implode(';', $updatedFieldsData);

        return sprintf(
            'Изменён %s, время изменения: %s, затронутые поля: %s',
            $name,
            $dateTime,
            $updatedFieldsData
        );
    }

    /**
     * Отправляет сообщения по сущностям
     *
     * @param array $mappedData
     * @param string $entityType
     * @param string $action
     *
     * @return void
     */
    private function sendNoteForEntities(array $mappedData, string $entityType, string $action, ?EventsCollections $history): void
    {
        $currentAccount = $this->amoCRMAdapter->getCurrentAccount();

        $mappedHistory = [];
        /** @var EventModel $historyData */
        foreach ($history as $historyData) {
            $mappedHistory[$historyData->getEntityId()][$historyData->getCreatedAt()] = $historyData->getValueBefore();
        }

        foreach ($mappedData as $entityData) {
            $message = null;

            if ($action === self::CREATE) {
                $message = $this->generateCreateNoteMessage(
                    (int)$entityData['responsible_user_id'],
                    $entityData['name'],
                    $entityData['date_create']
                );
            }

            if ($action === self::UPDATE) {
                $updateHistory = $this->getUpdatedFieldsData($mappedHistory, $entityData['id']);
                if ($updateHistory == null) {
                    continue;
                }

                $message = $this->generateUpdateNoteMessage(
                    $updateHistory,
                    $entityData['name'],
                    $entityData['date_updated'],
                );
            }

            if ($message === null) {
                $log_message = '[scip_data] ' . json_encode($entityData, JSON_UNESCAPED_UNICODE);
                $this->logger->log(LogLevel::ALERT, $log_message);

                continue;
            }

            $noteInput = new AddNoteInput(
                $entityType,
                (int)$entityData['id'],
                $message,
                $currentAccount->getCurrentUserId(),
                $currentAccount->getId()
            );
            $this->amoCRMAdapter->addNote($noteInput);
        }
    }

    /**
     * Получение обновлённых полей
     *
     * @param array $updateHistory
     * @param int $entityID
     *
     * @return array|null
     */
    private function getUpdatedFieldsData(array $updateHistory, int $entityID): ?array
    {
        $updateHistory = $updateHistory[$entityID] ?? null;
        if ($updateHistory == null) {
            return null;
        }

        ksort($updateHistory);
        $updateHistory = end($updateHistory);

        $updateHistory = $updateHistory[0];

        return array_values($updateHistory);
    }
}
