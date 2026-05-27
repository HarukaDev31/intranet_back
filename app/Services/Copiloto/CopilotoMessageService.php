<?php

namespace App\Services\Copiloto;

use App\Models\Copiloto\CopilotoConversacion;
use App\Models\Copiloto\WhatsappMessage;
use Carbon\Carbon;

class CopilotoMessageService
{
    /**
     * Crea/actualiza cabecera de conversación y persiste el mensaje.
     *
     * @return array{created:bool,message:WhatsappMessage|null}
     */
    public function persistMessage(array $data)
    {
        $phone = isset($data['phone']) ? $this->normalizePhone($data['phone']) : null;
        if (!$phone) {
            return ['created' => false, 'message' => null];
        }

        $bitrixChatId = $this->nullableString($data['bitrix_chat_id'] ?? null);
        $linea = $this->nullableString($data['linea'] ?? null);
        $body = isset($data['body']) ? (string) $data['body'] : '';
        $direction = ($data['direction'] ?? 'in') === 'out' ? 'out' : 'in';
        $source = in_array($data['source'] ?? '', ['bitrix', 'evolution', 'phone'], true)
            ? $data['source']
            : 'bitrix';
        $sentAt = $this->parseSentAt($data['sent_at'] ?? null);
        $bitrixMsgId = isset($data['bitrix_msg_id']) ? trim((string) $data['bitrix_msg_id']) : '';

        if ($bitrixMsgId === '') {
            $bitrixMsgId = 'generated_' . md5($phone . '|' . $sentAt . '|' . $body . '|' . $direction);
        }

        $threadKey = $this->buildThreadKey($phone, $bitrixChatId, $linea);

        $conversation = CopilotoConversacion::firstOrCreate(
            ['thread_key' => $threadKey],
            [
                'phone' => $phone,
                'bitrix_chat_id' => $bitrixChatId,
                'linea' => $linea,
                'contact_name' => $this->nullableString($data['contact_name'] ?? null),
                'messages_count' => 0,
            ]
        );

        if (!empty($data['contact_name']) && empty($conversation->contact_name)) {
            $conversation->contact_name = $this->nullableString($data['contact_name']);
        }

        $existing = WhatsappMessage::where('bitrix_msg_id', $bitrixMsgId)->first();
        $created = false;

        if ($existing) {
            $existing->fill([
                'conversation_id' => $conversation->id,
                'phone' => $phone,
                'bitrix_chat_id' => $bitrixChatId,
                'direction' => $direction,
                'body' => $body,
                'source' => $source,
                'linea' => $linea,
                'sent_at' => $sentAt,
            ])->save();
            $message = $existing;
        } else {
            $message = WhatsappMessage::create([
                'conversation_id' => $conversation->id,
                'phone' => $phone,
                'bitrix_chat_id' => $bitrixChatId,
                'bitrix_msg_id' => $bitrixMsgId,
                'direction' => $direction,
                'body' => $body,
                'source' => $source,
                'linea' => $linea,
                'sent_at' => $sentAt,
            ]);
            $created = true;
            $conversation->messages_count = (int) $conversation->messages_count + 1;
        }

        $this->refreshConversationHeader($conversation, $body, $direction, $sentAt);
        $conversation->save();

        return ['created' => $created, 'message' => $message];
    }

    private function refreshConversationHeader(CopilotoConversacion $conversation, $body, $direction, $sentAt)
    {
        $current = $conversation->last_message_at;
        if (!$current || $sentAt >= $current) {
            $conversation->last_message_at = $sentAt;
            $conversation->last_message_preview = mb_substr(trim((string) $body), 0, 500);
            $conversation->last_direction = $direction;
        }
    }

    private function parseSentAt($value)
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value === null || $value === '') {
            return now();
        }
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return now();
        }
    }

    private function normalizePhone($raw)
    {
        $clean = preg_replace('/\D+/', '', (string) $raw);
        if ($clean === '') {
            return null;
        }
        if (strlen($clean) === 9 && strpos($clean, '9') === 0) {
            return '51' . $clean;
        }
        return $clean;
    }

    private function nullableString($value)
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function buildThreadKey($phone, $bitrixChatId, $linea)
    {
        return hash('sha256', $phone . '|' . ($linea ?: '') . '|' . ($bitrixChatId ?: ''));
    }
}
