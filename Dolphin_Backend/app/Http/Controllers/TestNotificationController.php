<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestNotificationController extends Controller
{
    /**
     * Send a simple test receipt/notification payload. Used in development only.
     */
    public function sendReceipt(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Test receipt endpoint reached.',
        ]);
    }
}
