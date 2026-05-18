<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoporteTiChatController extends Controller
{
    /** @var SoporteTiService */
    protected $service;

    public function __construct(SoporteTiService $service)
    {
        $this->service = $service;
    }

    public function mensajes(Request $request, $chatUuid)
    {
        $limit = $request->query('limit', SoporteTiService::CHAT_PAGE_SIZE);
        $beforeId = $request->query('before_id');

        try {
            $result = $this->service->mensajesPaginados(
                $chatUuid,
                $limit,
                $beforeId,
                Auth::user()
            );
            return response()->json(array(
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ));
        } catch (AuthorizationException $e) {
            return response()->json(
                array('success' => false, 'message' => $e->getMessage() ?: 'No autorizado'),
                403
            );
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 404);
        }
    }

    public function marcarLeidos(Request $request, $chatUuid)
    {
        $request->validate(array(
            'mensaje_ids' => 'required|array|min:1|max:200',
            'mensaje_ids.*' => 'integer|min:1',
        ));

        try {
            $result = $this->service->marcarMensajesLeidos(
                $chatUuid,
                $request->input('mensaje_ids', array()),
                Auth::user()
            );

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(
                array('success' => false, 'message' => $e->getMessage() ?: 'No autorizado'),
                403
            );
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 404);
        }
    }

    public function infoMensaje($chatUuid, $mensajeId)
    {
        try {
            $data = $this->service->infoLecturaMensaje($chatUuid, (int) $mensajeId, Auth::user());

            return response()->json(array(
                'success' => true,
                'data' => $data,
            ));
        } catch (AuthorizationException $e) {
            return response()->json(
                array('success' => false, 'message' => $e->getMessage() ?: 'No autorizado'),
                403
            );
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 404);
        }
    }
}
