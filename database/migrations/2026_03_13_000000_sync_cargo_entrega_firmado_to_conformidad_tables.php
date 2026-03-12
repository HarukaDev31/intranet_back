<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza las firmas de cargo de entrega (cargo_entrega_pdf_firmado_url) que aún no están
 * en la tabla de conformidad correspondiente (Lima o Provincia), para que aparezcan en getEntregasDetalle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('contenedor_consolidado_cotizacion')
            ->whereNotNull('cargo_entrega_pdf_firmado_url')
            ->where('cargo_entrega_pdf_firmado_url', '!=', '')
            ->select('id as id_cotizacion', 'id_contenedor', 'cargo_entrega_pdf_firmado_url')
            ->get();

        foreach ($rows as $row) {
            $idCotizacion = (int) $row->id_cotizacion;
            $idContenedor = (int) $row->id_contenedor;
            $filePath = trim($row->cargo_entrega_pdf_firmado_url);

            $formLima = DB::table('consolidado_delivery_form_lima')
                ->where('id_cotizacion', $idCotizacion)
                ->where('id_contenedor', $idContenedor)
                ->value('id');

            $formProvince = DB::table('consolidado_delivery_form_province')
                ->where('id_cotizacion', $idCotizacion)
                ->where('id_contenedor', $idContenedor)
                ->value('id');

            if ($formProvince) {
                $tableName = 'consolidado_delivery_form_province_conformidad';
                $formIdField = 'consolidado_delivery_form_province_id';
                $formId = $formProvince;
            } elseif ($formLima) {
                $tableName = 'consolidado_delivery_form_lima_conformidad';
                $formIdField = 'consolidado_delivery_form_lima_id';
                $formId = $formLima;
            } else {
                continue;
            }

            $exists = DB::table($tableName)
                ->where('id_cotizacion', $idCotizacion)
                ->where('id_contenedor', $idContenedor)
                ->where('file_path', $filePath)
                ->exists();

            if ($exists) {
                continue;
            }

            $fullPath = public_path($filePath);
            $fileSize = (is_file($fullPath)) ? filesize($fullPath) : null;
            $filename = basename($filePath);

            DB::table($tableName)->insert([
                $formIdField => $formId,
                'id_cotizacion' => $idCotizacion,
                'id_contenedor' => $idContenedor,
                'file_path' => $filePath,
                'file_type' => 'application/pdf',
                'file_size' => $fileSize,
                'file_original_name' => $filename,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No se revierte: no podemos saber qué filas insertó esta migración sin una tabla de log.
        // Opcional: dejar vacío o eliminar por file_path LIKE 'entregas/cargo_entrega/%' si se desea rollback.
    }
};
