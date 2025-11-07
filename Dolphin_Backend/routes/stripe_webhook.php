<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeSubscriptionController;

Route::post('/stripe/webhook', [StripeSubscriptionController::class, 'handleWebhook']);
