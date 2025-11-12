<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestNotificationController extends Controller
{
    
    public function sendReceipt(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Test receipt endpoint reached.',
        ]);
    }
}
