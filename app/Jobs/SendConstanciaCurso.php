<?php
namespace App\Jobs;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Traits\UsesObjectStorage;
use App\Traits\WhatsappTrait;

/**
 * Summary of SendConstanciaCurso
 * Esta clase se encarga de generar y enviar una constancia de curso por WhatsApp.
 * Utiliza un job de Laravel para manejar la generación del PDF y el envío del mensaje.
 * La constancia incluye el nombre del participante y la fecha de emisión.
 * El PDF se genera a partir de una vista Blade y se envía como un archivo adjunto en un mensaje de WhatsApp.
 * La clase maneja errores y actualiza el estado del pedido de curso en la base de datos.
 * @package App\Jobs
 * @author Tu Nombre
 * @version 1.0
 * @since 2023-10-01
 * @param string $phoneNumberId El ID del número de teléfono de WhatsApp al que se enviará la constancia.
 * @param mixed $pedidoCurso El objeto del pedido de curso que contiene la información del participante.
 */
class SendConstanciaCurso implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UsesObjectStorage, WhatsappTrait;

    private $phoneNumberId;
    private $pedidoCurso;
    private $table = 'pedido_curso';

    public function __construct(
        string $phoneNumberId = "51912705923@c.us",
        $pedidoCurso = null
    ) {
        //first trim number and remove +
        $phoneNumberId = trim($phoneNumberId);
        $phoneNumberId = str_replace('+', '', $phoneNumberId);
        //later if number has low 9 digits, add 51
        if (strlen($phoneNumberId) <=9) {
            $phoneNumberId = '51' . $phoneNumberId;
        }
        //check if number has @c.us
        if (substr($phoneNumberId, -5) !== '@c.us') {
            $phoneNumberId .= '@c.us'; // Asegurar que el número tenga el formato correcto
        }
        $this->phoneNumberId = $phoneNumberId;
        $this->pedidoCurso   = $pedidoCurso;
    }

    public function handle()
    {
        $pdfPath = null;

        try {
            if (! $this->pedidoCurso) {
                Log::error('El pedido de curso no está definido', [
                    'phoneNumberId' => $this->phoneNumberId,
                ]);
                $this->fail(new \Exception('El pedido de curso no está definido'));
                return;
            }

            $nombreParticipante = $this->pedidoCurso->No_Entidad ?? 'Participante';
            $fechaEmision       = $this->pedidoCurso->Fe_Fin ?? now()->format('Y-m-d');
            $emailParticipante  = $this->pedidoCurso->Txt_Email_Entidad ?? 'harukakasugano31@gmail.com';
            
            // Generar PDF desde el Blade
            $pdfPath = $this->generatePDF($nombreParticipante, $fechaEmision);
            
            // Mensaje de WhatsApp
            $mensaje = "🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.\n\n" .
                "Equivalente a 12 horas académicas.\n" .
                "Dictado por nuestros expertos en comercio internacional.\n\n" .
                "¡Gracias por tu participación! 🎉";
            
            // Enviar el PDF por WhatsApp usando el trait
            $response = $this->sendMedia(
                $pdfPath,
                'application/pdf',
                $mensaje,
                $this->phoneNumberId
            );

            if ($response && isset($response['status']) && $response['status']) {
                // WhatsApp se envió exitosamente
                Log::info('Constancia de WhatsApp enviada exitosamente', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso'   => $this->pedidoCurso->ID_Pedido_Curso,
                ]);

                // Actualizar estado a ENVIADO
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                        'from_intranet' => 1,
                    ]);

                // Enviar correo SOLO después de que WhatsApp se envíe exitosamente
                $this->sendEmailWithErrorHandling($pdfPath, $emailParticipante);

                return;
            } else {
                Log::error('Error al enviar constancia por WhatsApp', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso'   => $this->pedidoCurso->ID_Pedido_Curso,
                    'response' => $response
                ]);

                // Marcar como enviado aunque falle WhatsApp
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                        'from_intranet' => 1,
                    ]);

                return;
            }
        } catch (\Exception $e) {
            Log::error('Error en SendConstanciaCurso: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'pedidoCurso'   => $this->pedidoCurso ? $this->pedidoCurso->ID_Pedido_Curso : null,
                'error'         => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        } finally {
            // Guardar la URL del PDF si se generó
            if ($pdfPath && file_exists($pdfPath)) {
                try {
                    // Usar solo la ruta relativa definida en generatePDF
                    // Formato: Cursos/constancias/constancia_nombre_timestamp.pdf
                    $fileName = basename($pdfPath);
                    $relativePath = 'Cursos/constancias/' . $fileName;
                    
                    DB::table($this->table)
                        ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                        ->update([
                            'url_constancia' => $relativePath, // Ruta relativa para usar con generateImageUrl
                        ]);
                } catch (\Exception $e) {
                    Log::error('Error al guardar la URL del PDF: ' . $e->getMessage(), [
                        'pdfPath' => $pdfPath,
                    ]);
                }
            }
        }
    }

    private function generatePDF(string $nombre, string $fecha): string
    {
        try {
            //encode base img
            $fondoImg = base64_encode(file_get_contents(public_path('img/fondo.png')));
            //convert fecha d/m/y to day de month de year
            $fecha = date('d \d\e F \d\e Y', strtotime($fecha));
            //reemplazar mes en español
            $meses = [
                'January'   => 'Enero',
                'February'  => 'Febrero',
                'March'     => 'Marzo',
                'April'     => 'Abril',
                'May'       => 'Mayo',
                'June'      => 'Junio',
                'July'      => 'Julio',
                'August'    => 'Agosto',
                'September' => 'Septiembre',
                'October'   => 'Octubre',
                'November'  => 'Noviembre',
                'December'  => 'Diciembre',
            ];
            $fecha = str_replace(array_keys($meses), array_values($meses), $fecha);

            // Crear el PDF
            $options = new Options();
            $options->set('fontDir', storage_path('fonts/'));
            $options->set('fontCache', storage_path('fonts/'));
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            //set paper letter landscape
            $options->set('defaultPaperSize', 'letter');
            $options->set('defaultPaperOrientation', 'landscape');
            $dompdf = new Dompdf($options);

            // Registrar la fuente
            $fontMetrics = $dompdf->getFontMetrics();
            $fontMetrics->registerFont(
                ['family' => 'lucide-handwriting', 'style' => 'normal', 'weight' => 'normal'],
                storage_path('fonts/lucide-handwriting-regular.ttf')
            );

            // Renderizar la vista
            $html = view('constancia', [
                'nombre'   => $nombre,
                'fecha'    => $fecha,
                'fondoImg' => $fondoImg,
            ])->render();
            
            $dompdf->loadHtml($html);

            $fileName = 'constancia_' . Str::slug($nombre) . '_' . time() . '.pdf';
            $storagePath = 'Cursos/constancias';
            $relativeFilePath = $storagePath . '/' . $fileName;
            
            //set dpi 150
            $dompdf->set_option('dpi', 150);
            $dompdf->setPaper('letter', 'landscape');
            // Renderizar y guardar el PDF
            $dompdf->render();
            $this->storagePutContents($relativeFilePath, $dompdf->output());
            $pdfPath = $this->storageLocalPath($relativeFilePath);

            Log::info('PDF generado exitosamente', [
                'path'   => $pdfPath,
                'nombre' => $nombre,
                'size'   => filesize($pdfPath) . ' bytes',
            ]);

            return $pdfPath;
        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());
            throw new \Exception('Error al generar la constancia PDF: ' . $e->getMessage());
        }
    }

    private function sendEmailWithErrorHandling(string $pdfPath, string $emailParticipante)
    {
        try {
            Mail::raw('🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.

                    Equivalente a 12 horas académicas.
                    Dictado por nuestros expertos en comercio internacional.

                    ¡Gracias por tu participación! ', function ($message) use ($pdfPath, $emailParticipante) {
                $message->from('noreply@lae.one', 'Probusiness')
                    ->to($emailParticipante)
                    ->subject('Constancia de Curso-Probusiness')
                    ->attach($pdfPath, [
                        'as'   => basename($pdfPath),
                        'mime' => 'application/pdf',
                    ]);
            });
            Log::info('Correo enviado exitosamente', ['email' => $emailParticipante]);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo: ' . $e->getMessage(), [
                'email' => $emailParticipante,
                'error' => $e->getTraceAsString(),
            ]);
            // NO lanzar excepción - el job debe continuar como exitoso
        }
    }

    public function tags()
    {
        return [
            'send-constancia-curso-job',
            'phoneNumberId:' . $this->phoneNumberId,
            'pedidoCurso:' . ($this->pedidoCurso ? $this->pedidoCurso->ID_Pedido_Curso : 'null'),
        ];
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job SendConstanciaCurso falló completamente', [
            'phoneNumberId' => $this->phoneNumberId,
            'pedidoCurso'   => $this->pedidoCurso ? $this->pedidoCurso->ID_Pedido_Curso : null,
            'error'         => $exception->getMessage(),
        ]);
    }
}

