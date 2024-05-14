<?php

namespace App\Service;

use AmoCRM\Helpers\EntityTypesInterface;
use App\Adapter\AmoCRMAdapter;
use App\Adapter\Data\Input\AddNoteInput;
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

        [$mappedData, $action] = $this->mapHookData($data, 'leads');

        $this->sendNoteForEntities($mappedData, $entityType, $action);
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

        $this->sendNoteForEntities($mappedData, $entityType, $action);
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
        $mappedData = array_map(function (array $lead) {
            return [
                'id' => $lead['id'],
                'name' => $lead['name'],
                'created_user_id' => $lead['created_user_id'],
                'modified_user_id' => $lead['modified_user_id'] ?? 0,
                'date_create' => date('Y-m-d H:i:s', $lead['date_create']),
                'date_updated' => date('Y-m-d H:i:s', $lead['last_modified']),
                'responsible_user_id' => $lead['responsible_user_id'] ?? 0
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
        foreach ($updatedFields as $fieldName => $updatedField) {
            $updatedFieldsData[] = $fieldName . ' обновлено на: ' . $updatedField;
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
    private function sendNoteForEntities(array $mappedData, string $entityType, string $action): void
    {
        $currentAccount = $this->amoCRMAdapter->getCurrentAccount();

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
                $message = $this->generateUpdateNoteMessage(
                    ['name' => $entityData['name']],
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
}
