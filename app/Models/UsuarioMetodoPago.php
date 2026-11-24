<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Método de pago preferido de un usuario interno (CFG-26-0056).
 *
 * @property int $id
 * @property int $ID_Usuario
 * @property string $metodo
 * @property string $numero
 */
class UsuarioMetodoPago extends Model
{
    protected $table = 'usuario_metodo_pago';

    protected $fillable = ['ID_Usuario', 'metodo', 'numero'];

    /** Billeteras digitales disponibles por ahora. */
    public const METODOS = [
        'yape' => 'Yape',
        'plin' => 'Plin',
    ];

    public const METODO_POR_DEFECTO = 'yape';

    /**
     * Método preferido del usuario; si no lo configuró, Yape con el celular de su cuenta.
     *
     * @return array{metodo: string, metodo_label: string, numero: string|null, configurado: bool}
     */
    public static function preferidoDe(?Usuario $usuario): array
    {
        $registro = $usuario
            ? self::where('ID_Usuario', $usuario->ID_Usuario)->first()
            : null;

        if ($registro) {
            return [
                'metodo' => $registro->metodo,
                'metodo_label' => self::METODOS[$registro->metodo] ?? $registro->metodo,
                'numero' => $registro->numero,
                'configurado' => true,
            ];
        }

        return [
            'metodo' => self::METODO_POR_DEFECTO,
            'metodo_label' => self::METODOS[self::METODO_POR_DEFECTO],
            'numero' => self::numeroLocal($usuario ? $usuario->getAttribute('Nu_Celular') : null),
            'configurado' => false,
        ];
    }

    /** Celular en formato local de 9 dígitos (quita espacios y el prefijo 51 de Perú). */
    public static function numeroLocal($celular): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $celular) ?? '';
        if (strlen($digitos) === 11 && strpos($digitos, '51') === 0) {
            $digitos = substr($digitos, 2);
        }

        return $digitos !== '' ? $digitos : null;
    }
}
