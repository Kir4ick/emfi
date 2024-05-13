<?php

namespace App\Service;

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

    public function processLead(array $data): void
    {
        # Мапим кашу в удобный вид
        [$mappedData, $action] = $this->mapLeads($data);

        foreach ($mappedData as $leadData) {
            $message = null;

            if ($action === self::CREATE) {
                $message = $this->generateCreateNoteMessage(
                    (int)$leadData['responsible_user_id'],
                    $leadData['name'],
                    $leadData['date_create']
                );
            }

            if ($action === self::UPDATE) {
                $message = $this->generateUpdateNoteMessage(
                    $leadData['updated'],
                    $leadData['name'],
                    $leadData['date_updated'],
                );
            }

            if ($message === null) {
                $log_message = '[scip_lead] ' . json_encode($leadData, JSON_UNESCAPED_UNICODE);
                $this->logger->log(LogLevel::ALERT, $log_message);

                continue;
            }

            $noteInput = new AddNoteInput('leads', (int)$leadData['id'], $message);
            $this->amoCRMAdapter->addNote($noteInput);
        }
    }

    private function mapLeads(array $data): array
    {
        $leadsData = $data['leads'] ?? null;
        if ($leadsData == null) {
            throw new BadRequestHttpException();
        }

        # Вытаскиваем событие
        $action = array_keys($leadsData)[0] ?? null;
        if ($action == null) {
            throw new BadRequestHttpException();
        }

        $leadsData = $leadsData[$action];

        $mappedLeads = array_map(function (array $lead) {
            return [
                'id' => $lead['id'],
                'name' => $lead['name'],
                'created_user_id' => $lead['created_user_id'],
                'modified_user_id' => $lead['modified_user_id'],
                'date_create' => date('Y-m-d H:i:s', strtotime($lead['date_create'])),
                'date_updated' => date('Y-m-d H:i:s', strtotime($lead['last_modified'])),
                'responsible_user_id' => $lead['responsible_user_id']
            ];
        }, $leadsData);

        return [$mappedLeads, $action];
    }

    private function generateCreateNoteMessage(
        int $responsibleUserID,
        string $name,
        string $dateTime
    ): ?string {
        $accountModel = $this->amoCRMAdapter->getAccount($responsibleUserID);
        if ($accountModel == null) {
            return null;
        }

        return sprintf(
            'Добавлен %s, ответственный пользователь: %s, время создания: %s',
            $name,
            $accountModel->getName(),
            $dateTime
        );
    }

    private function generateUpdateNoteMessage(array $updatedFields, string $name, string $dateTime): string
    {


        return sprintf(
            'Изменён %s, время изменения: %s, затронутые поля: %s',
            $name,
            $dateTime
        );
    }

    public function processUpdateContact()
    {

    }

    public function processCreateContact()
    {

    }
}
