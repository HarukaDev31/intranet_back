<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;
use App\Helpers\DateHelper;

class CursosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID Pedido',
            'Fecha Registro',
            'Cliente',
            'Tipo Documento',
            'Número Documento',
            'Celular',
            'Email',
            'Tipo Curso',
            'Campaña',
            'Total',
            'Total Pagos',
            'Estado Pago',
            'Fecha Nacimiento',
            'Red Social',
            'Distrito',
            'Provincia',
            'Departamento',
            'País',
            'Sexo',
            'Edad',
            'Moneda',
            'Usuario Moodle'
        ];
    }

    public function map($curso): array
    {
        return [
            $curso['ID_Pedido_Curso'],
            $curso['Fe_Registro'],
            $curso['No_Entidad'],
            $curso['No_Tipo_Documento_Identidad_Breve'],
            $curso['Nu_Documento_Identidad'],
            $curso['Nu_Celular_Entidad'],
            $curso['Txt_Email_Entidad'],
            $curso['tipo_curso'] == 0 ? 'Virtual' : 'En vivo',
            $curso['Nombre_Campana'] ?: $curso['ID_Campana'],
            $curso['Ss_Total'],
            $curso['total_pagos'],
            $this->getEstadoPagoLabel($curso['estado_pago']),
            $curso['Fe_Nacimiento'],
            $this->getRedSocialLabel($curso['Nu_Como_Entero_Empresa']),
            $curso['No_Distrito'],
            $curso['No_Provincia'],
            $curso['No_Departamento'],
            $curso['No_Pais'],
            $curso['Nu_Tipo_Sexo'] == 1 ? 'Masculino' : 'Femenino',
            $curso['Nu_Edad'],
            $curso['No_Signo'],
            $curso['No_Usuario']
        ];
    }

    private function getEstadoPagoLabel($estado)
    {
        $estados = [
            'pendiente' => 'Pendiente',
            'adelanto' => 'Adelanto',
            'pagado' => 'Pagado',
            'sobrepagado' => 'Sobrepagado',
            'constancia' => 'Constancia'
        ];

        return $estados[$estado] ?? $estado;
    }

    private function getRedSocialLabel($redSocial)
    {
        $redesSociales = [
            1 => 'TikTok',
            2 => 'Facebook',
            3 => 'Instagram',
            4 => 'YouTube',
            5 => 'Familiares/Amigos',
            6 => 'LinkedIn',
            7 => 'Google',
            8 => 'Otros'
        ];

        return $redesSociales[$redSocial] ?? 'No especificado';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // ID Pedido
            'B' => 15,  // Fecha Registro
            'C' => 30,  // Cliente
            'D' => 15,  // Tipo Documento
            'E' => 15,  // Número Documento
            'F' => 15,  // Celular
            'G' => 25,  // Email
            'H' => 12,  // Tipo Curso
            'I' => 15,  // Campaña
            'J' => 12,  // Total
            'K' => 12,  // Total Pagos
            'L' => 12,  // Estado Pago
            'M' => 15,  // Fecha Nacimiento
            'N' => 20,  // Red Social
            'O' => 20,  // Distrito
            'P' => 20,  // Provincia
            'Q' => 20,  // Departamento
            'R' => 15,  // País
            'S' => 10,  // Sexo
            'T' => 8,   // Edad
            'U' => 10,  // Moneda
            'V' => 20,  // Usuario Moodle
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Aplicar bordes a todas las celdas con datos
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Congelar la primera fila
                $sheet->freezePane('A2');

                // Aplicar filtros automáticos
                $sheet->setAutoFilter('A1:' . $highestColumn . $highestRow);
            },
        ];
    }
}
