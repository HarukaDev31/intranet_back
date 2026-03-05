<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Normaliza la tabla clientes eliminando duplicados:
 * - Mismo número de teléfono (o equivalentes: 934958839 y 51934958839)
 * - Mismo documento (DNI)
 * - Mismo RUC
 * - Mismo correo
 * Se conserva el registro más antiguo (menor id) y se borran los más nuevos.
 */
class NormalizeClientesRemoveDuplicates extends Migration
{
    /**
     * Normalizar teléfono a forma canónica: solo dígitos; si es 11 dígitos empezando en 51, usar los 9 últimos.
     */
    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '51')) {
            return substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        $clientes = DB::table('clientes')
            ->select('id', 'telefono', 'documento', 'ruc', 'correo', 'created_at')
            ->orderBy('id')
            ->get();

        $canonicalPhoneToRows = [];
        $documentoToRows = [];
        $rucToRows = [];
        $correoToRows = [];

        foreach ($clientes as $row) {
            $canonical = $this->normalizePhone($row->telefono);
            if ($canonical !== null && strlen($canonical) >= 7) {
                $canonicalPhoneToRows[$canonical][] = $row;
            }
            $doc = $row->documento !== null && trim((string) $row->documento) !== ''
                ? trim((string) $row->documento)
                : null;
            if ($doc !== null) {
                $documentoToRows[$doc][] = $row;
            }
            $ruc = $row->ruc !== null && trim((string) $row->ruc) !== ''
                ? trim((string) $row->ruc)
                : null;
            if ($ruc !== null) {
                $rucToRows[$ruc][] = $row;
            }
            $correo = $row->correo !== null && trim((string) $row->correo) !== ''
                ? strtolower(trim((string) $row->correo))
                : null;
            if ($correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $correoToRows[$correo][] = $row;
            }
        }

        $idsToDelete = [];
        $mergeInto = [];

        foreach ($canonicalPhoneToRows as $rows) {
            if (count($rows) <= 1) {
                continue;
            }
            usort($rows, fn($a, $b) => $a->id <=> $b->id);
            $keepId = $rows[0]->id;
            for ($i = 1; $i < count($rows); $i++) {
                $idsToDelete[$rows[$i]->id] = true;
                if (!isset($mergeInto[$rows[$i]->id]) || $keepId < $mergeInto[$rows[$i]->id]) {
                    $mergeInto[$rows[$i]->id] = $keepId;
                }
            }
        }

        foreach ($documentoToRows as $rows) {
            if (count($rows) <= 1) {
                continue;
            }
            usort($rows, fn($a, $b) => $a->id <=> $b->id);
            $keepId = $rows[0]->id;
            for ($i = 1; $i < count($rows); $i++) {
                $idsToDelete[$rows[$i]->id] = true;
                $target = $mergeInto[$rows[$i]->id] ?? $keepId;
                $mergeInto[$rows[$i]->id] = min($target, $keepId);
            }
        }

        foreach ($rucToRows as $rows) {
            if (count($rows) <= 1) {
                continue;
            }
            usort($rows, fn($a, $b) => $a->id <=> $b->id);
            $keepId = $rows[0]->id;
            for ($i = 1; $i < count($rows); $i++) {
                $idsToDelete[$rows[$i]->id] = true;
                $target = $mergeInto[$rows[$i]->id] ?? $keepId;
                $mergeInto[$rows[$i]->id] = min($target, $keepId);
            }
        }

        foreach ($correoToRows as $rows) {
            if (count($rows) <= 1) {
                continue;
            }
            usort($rows, fn($a, $b) => $a->id <=> $b->id);
            $keepId = $rows[0]->id;
            for ($i = 1; $i < count($rows); $i++) {
                $idsToDelete[$rows[$i]->id] = true;
                $target = $mergeInto[$rows[$i]->id] ?? $keepId;
                $mergeInto[$rows[$i]->id] = min($target, $keepId);
            }
        }

        $idsToDelete = array_keys($idsToDelete);
        if (count($idsToDelete) === 0) {
            return;
        }

        $clientesABorrar = DB::table('clientes')
            ->whereIn('id', $idsToDelete)
            ->orderBy('id')
            ->get(['id', 'nombre', 'telefono', 'documento', 'ruc', 'correo']);

        $total = count($clientesABorrar);
        Log::info('[NormalizeClientes] Clientes duplicados que se borran (' . $total . ' registros):');
        echo "\n[NormalizeClientes] Se borran {$total} clientes duplicados:\n";
        foreach ($clientesABorrar as $c) {
            $line = sprintf(
                '  id=%s | nombre=%s | tel=%s | doc=%s | ruc=%s | correo=%s',
                $c->id,
                $c->nombre ?? '',
                $c->telefono ?? '',
                $c->documento ?? '',
                $c->ruc ?? '',
                $c->correo ?? ''
            );
            Log::info('[NormalizeClientes] ' . $line);
            echo $line . "\n";
        }
        echo "\n";

        // Reasignar referencias para cumplir la FK antes de borrar
        foreach ($idsToDelete as $deletedId) {
            $targetId = $mergeInto[$deletedId] ?? null;
            if ($targetId === null) {
                continue;
            }
            if (Schema::hasTable('pedido_curso') && Schema::hasColumn('pedido_curso', 'id_cliente')) {
                DB::table('pedido_curso')->where('id_cliente', $deletedId)->update(['id_cliente' => $targetId]);
            }
            if (Schema::hasTable('contenedor_consolidado_cotizacion') && Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente')) {
                DB::table('contenedor_consolidado_cotizacion')->where('id_cliente', $deletedId)->update(['id_cliente' => $targetId]);
            }
            if (Schema::hasTable('calculadora_importacion') && Schema::hasColumn('calculadora_importacion', 'id_cliente')) {
                DB::table('calculadora_importacion')->where('id_cliente', $deletedId)->update(['id_cliente' => $targetId]);
            }
            if (Schema::hasTable('consolidado_cotizacion_aduana_tramites') && Schema::hasColumn('consolidado_cotizacion_aduana_tramites', 'id_cliente')) {
                DB::table('consolidado_cotizacion_aduana_tramites')->where('id_cliente', $deletedId)->update(['id_cliente' => $targetId]);
            }
        }

        DB::table('clientes')->whereIn('id', $idsToDelete)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversible: los registros eliminados no se pueden recuperar de forma determinista.
    }
}
