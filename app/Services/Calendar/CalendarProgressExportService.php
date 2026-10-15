<?php

namespace App\Services\Calendar;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel de la tabla de progreso (mismas columnas que la pantalla, sin paginar).
 */
class CalendarProgressExportService
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function download(array $events, $usaConsolidado)
    {
        $usaConsolidado = (bool) $usaConsolidado;
        $headers = array('Actividad');
        if ($usaConsolidado) {
            $headers[] = '# Consolidado';
        }
        $headers = array_merge($headers, array(
            'Estado',
            'Prioridad',
            'Fecha Inicio',
            'Fecha Fin',
            'Duración',
            'Responsables',
        ));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Progreso');

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $lastColumn = $this->columnLetter(count($headers));
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray(array(
            'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
            'fill' => array(
                'fillType' => Fill::FILL_SOLID,
                'startColor' => array('rgb' => '1F4E79'),
            ),
            'alignment' => array('vertical' => Alignment::VERTICAL_CENTER),
        ));

        $row = 2;
        foreach ($events as $event) {
            $values = array(isset($event['name']) ? (string) $event['name'] : '');
            if ($usaConsolidado) {
                $contenedor = isset($event['contenedor']['nombre']) ? (string) $event['contenedor']['nombre'] : '';
                $values[] = $contenedor !== '' ? $contenedor : '-';
            }
            $values[] = $this->statusLabel(isset($event['status']) ? $event['status'] : null);
            $values[] = $this->priorityLabel(isset($event['priority']) ? $event['priority'] : null);
            $values[] = $this->formatDate(isset($event['start_date']) ? $event['start_date'] : null);
            $values[] = $this->formatDate(isset($event['end_date']) ? $event['end_date'] : null);
            $values[] = $this->workingDays($event) . ' días';
            $values[] = $this->responsableNames(isset($event['charges']) ? $event['charges'] : array());

            foreach ($values as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, $row, $value);
            }
            $row++;
        }

        $lastRow = max(1, $row - 1);
        $sheet->setAutoFilter('A1:' . $lastColumn . $lastRow);
        $sheet->freezePane('A2');
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        $fileName = 'progreso_calendario_' . date('Y-m-d') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, array(
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $charges
     */
    private function responsableNames($charges)
    {
        $names = array();
        if (!is_array($charges)) {
            return '';
        }
        foreach ($charges as $charge) {
            $nombre = '';
            if (isset($charge['user']['nombre'])) {
                $nombre = trim((string) $charge['user']['nombre']);
            }
            if ($nombre !== '') {
                $names[] = $nombre;
            }
        }

        return implode(', ', $names);
    }

    private function statusLabel($status)
    {
        $labels = array(
            'PENDIENTE' => 'Pendiente',
            'PROGRESO' => 'En progreso',
            'COMPLETADO' => 'Completado',
        );
        $key = strtoupper(trim((string) $status));

        return isset($labels[$key]) ? $labels[$key] : ($key !== '' ? $key : '');
    }

    private function priorityLabel($priority)
    {
        $labels = array(0 => 'Bajo', 1 => 'Medio', 2 => 'Alto');
        $key = (int) $priority;

        return isset($labels[$key]) ? $labels[$key] : '';
    }

    private function formatDate($iso)
    {
        $value = substr(trim((string) $iso), 0, 10);
        if ($value === '') {
            return '';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date ? $date->format('d/m/Y') : $value;
    }

    /**
     * Días hábiles (lun–vie) entre inicio y fin, inclusive. Mínimo 1.
     *
     * @param  array<string, mixed>  $event
     */
    private function workingDays(array $event)
    {
        $start = isset($event['start_date']) ? substr((string) $event['start_date'], 0, 10) : '';
        $end = isset($event['end_date']) ? substr((string) $event['end_date'], 0, 10) : '';
        if ($start === '' || $end === '') {
            return 1;
        }
        $from = \DateTime::createFromFormat('Y-m-d', $start);
        $to = \DateTime::createFromFormat('Y-m-d', $end);
        if (!$from || !$to || $from > $to) {
            return 1;
        }
        $count = 0;
        while ($from <= $to) {
            $weekday = (int) $from->format('N');
            if ($weekday < 6) {
                $count++;
            }
            $from->modify('+1 day');
        }

        return $count > 0 ? $count : 1;
    }

    private function columnLetter($index)
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = (int) floor($index / 26);
        }

        return $letter;
    }
}
