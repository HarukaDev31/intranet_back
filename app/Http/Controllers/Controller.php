<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Intranet Probusiness API",
 *     version="1.0.0",
 *     description="API de Intranet para gestión de carga consolidada, clientes, calculadora de importación, cursos, notificaciones y más.",
 *     @OA\Contact(
 *         email="soporte@probusiness.com.pe",
 *         name="Soporte Técnico Probusiness"
 *     ),
 *     @OA\License(
 *         name="Propietario",
 *         url="https://probusiness.com.pe"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="Servidor API Principal"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Autenticación JWT. Ingrese el token sin el prefijo 'Bearer '"
 * )
 * 
 * @OA\Tag(
 *     name="Autenticación",
 *     description="Endpoints de autenticación para usuarios internos y externos"
 * )
 * @OA\Tag(
 *     name="Autenticación Clientes",
 *     description="Endpoints de autenticación para clientes externos"
 * )
 * @OA\Tag(
 *     name="Menú",
 *     description="Gestión de menús del sistema"
 * )
 * @OA\Tag(
 *     name="Clientes",
 *     description="Gestión de clientes"
 * )
 * @OA\Tag(
 *     name="Productos",
 *     description="Gestión de productos"
 * )
 * @OA\Tag(
 *     name="Carga Consolidada",
 *     description="Gestión de carga consolidada y cotizaciones"
 * )
 * @OA\Tag(
 *     name="Calculadora Importación",
 *     description="Calculadora de costos de importación"
 * )
 * @OA\Tag(
 *     name="Cursos",
 *     description="Gestión de cursos y capacitaciones"
 * )
 * @OA\Tag(
 *     name="Notificaciones",
 *     description="Sistema de notificaciones"
 * )
 * @OA\Tag(
 *     name="Calendario",
 *     description="Gestión del calendario"
 * )
 * @OA\Tag(
 *     name="Campañas",
 *     description="Gestión de campañas"
 * )
 * @OA\Tag(
 *     name="Noticias",
 *     description="Gestión de noticias del sistema"
 * )
 * @OA\Tag(
 *     name="Perfil Usuario",
 *     description="Gestión del perfil de usuario"
 * )
 * @OA\Tag(
 *     name="Empresa Usuario",
 *     description="Gestión de datos de empresa del usuario"
 * )
 * @OA\Tag(
 *     name="Delivery",
 *     description="Gestión de entregas y delivery"
 * )
 * @OA\Tag(
 *     name="Contenedores",
 *     description="Gestión de contenedores"
 * )
 * @OA\Tag(
 *     name="Ubicaciones",
 *     description="Gestión de ubicaciones geográficas"
 * )
 * @OA\Tag(
 *     name="Pagos",
 *     description="Gestión de pagos y transacciones"
 * )
 * @OA\Tag(
 *     name="Factura y Guía",
 *     description="Gestión de facturas y guías de remisión"
 * )
 * @OA\Tag(
 *     name="Tipos de Cliente",
 *     description="Clasificación de tipos de cliente"
 * )
 * @OA\Tag(
 *     name="Dashboard Usuario",
 *     description="Dashboard personalizado del usuario"
 * )
 * @OA\Tag(
 *     name="Dashboard Ventas",
 *     description="Dashboard de ventas y métricas"
 * )
 * @OA\Tag(
 *     name="Importación",
 *     description="Importación de datos desde archivos Excel"
 * )
 * @OA\Tag(
 *     name="Documentación",
 *     description="Gestión de documentación de carga"
 * )
 * @OA\Tag(
 *     name="Cotización Final",
 *     description="Gestión de cotizaciones finales"
 * )
 * @OA\Tag(
 *     name="Clientes Carga Consolidada",
 *     description="Gestión de clientes dentro de carga consolidada"
 * )
 * @OA\Tag(
 *     name="Usuarios",
 *     description="Gestión de usuarios del sistema"
 * )
 * @OA\Tag(
 *     name="Regulaciones",
 *     description="Gestión de regulaciones de importación"
 * )
 * @OA\Tag(
 *     name="Commons",
 *     description="Endpoints comunes y utilidades"
 * )
 * @OA\Tag(
 *     name="Google Sheets",
 *     description="Integración con Google Sheets"
 * )
 * @OA\Tag(
 *     name="Cotizaciones Proveedor",
 *     description="Gestión de cotizaciones de proveedores"
 * )
 * @OA\Tag(
 *     name="Aduana",
 *     description="Gestión de formularios de aduana"
 * )
 * @OA\Tag(
 *     name="Entregas",
 *     description="Gestión de entregas y horarios"
 * )
 * @OA\Tag(
 *     name="Cotizaciones",
 *     description="Gestión de cotizaciones"
 * )
 * @OA\Tag(
 *     name="Archivos",
 *     description="Servicio de archivos y almacenamiento"
 * )
 * @OA\Tag(
 *     name="Broadcasting",
 *     description="WebSocket broadcasting y notificaciones en tiempo real"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
