<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Evento;
use App\Models\Calendar\TaskDay;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class CalendarController extends Controller
{
    public function getEvents(Request $request) { /* ver app/Http/Controllers/Calendar/ */ }
    public function getEvent($id) { /* ... */ }
    public function createEvent(Request $request) { /* ... */ }
    public function updateEvent(Request $request, $id) { /* ... */ }
    public function updateTaskDay(Request $request, $taskDayId) { /* ... */ }
    public function deleteEvent(Request $request, $id) { /* ... */ }
    public function moveEvent(Request $request, $id) { /* ... */ }
    public function deleteTaskDay($taskDayId) { /* ... */ }
}
