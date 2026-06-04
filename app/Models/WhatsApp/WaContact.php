<?php

namespace App\Models\WhatsApp;

use Illuminate\Database\Eloquent\Model;

/**
 * Directorio global de contactos WhatsApp (por teléfono).
 * La sesión, último mensaje y ventana viven en wa_*_conversations.
 */
class WaContact extends Model
{
    protected $table = 'wa_contacts';

    protected $fillable = [
        'phone_e164',
        'contact_name',
        'source',
        'origin_module',
        'origin_session_id',
        'origin_line_number',
        'origin_line_label',
        'wa_inbox_conversation_id',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
