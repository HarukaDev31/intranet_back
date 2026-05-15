<?php

namespace App\Http\Controllers\SoporteTi;

use App\Http\Controllers\Controller;
use App\Services\SoporteTi\SoporteTiService;
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
        } catch (\Exception $e) {
            return response()->json(array('success' => false, 'message' => $e->getMessage()), 404);
        }
    }
}
