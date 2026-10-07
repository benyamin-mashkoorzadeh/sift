<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        DB::select('SELECT 1');

        return response()->json([
            'data' => [
                'status' => 'ok',
                'database' => 'connected',
            ],
        ]);
    }
}
