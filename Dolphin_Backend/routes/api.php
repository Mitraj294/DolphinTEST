<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentResponseController;
use App\Http\Controllers\AssessmentResultController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SendAgreementController;
use App\Http\Controllers\SendAssessmentController;
use App\Http\Controllers\StripeSubscriptionController;
use App\Http\Controllers\Billing\BillingController as BillingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookLogController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES - No Authentication Required
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json([
            'status' => 'ok',
            'service' => 'dolphin-backend',
            'timestamp' => now()->toISOString(),
            'database' => 'connected',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'service' => 'dolphin-backend',
            'timestamp' => now()->toISOString(),
            'database' => 'disconnected',
            'message' => $e->getMessage(),
        ], 503);
    }
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::prefix('password')->group(function () {
    Route::post('/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/email', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('/reset', [AuthController::class, 'resetPassword']);
});

Route::post('/stripe/webhook', [StripeSubscriptionController::class, 'handleWebhook']);
Route::post('/stripe/checkout-session', [StripeSubscriptionController::class, 'createCheckoutSession']);
Route::get('/stripe/session', [StripeSubscriptionController::class, 'getSessionDetails']);

Route::prefix('assessments')->group(function () {
    Route::get('/{id}/summary', [AssessmentController::class, 'summary']);
    // REMOVED: AssessmentAnswerLinkController routes (controller deleted - used non-existent members table)
});

Route::prefix('leads')->group(function () {
    Route::get('/find-us-options', [LeadController::class, 'findUsOptions']);
    Route::get('/prefill', [LeadController::class, 'prefill']);
    Route::post('/send-assessment', [SendAssessmentController::class, 'send']);
    Route::post('/send-agreement', [SendAgreementController::class, 'send']);
    Route::get('/guest-validate', [SendAgreementController::class, 'validateGuest']);
});

Route::prefix('email-template')->group(function () {
    Route::get('/lead-registration', [LeadController::class, 'leadRegistration']);
    Route::get('/lead-agreement', [LeadController::class, 'leadAgreement']);
});

// REMOVED: ScheduledEmailController routes (controller deleted - used non-existent members table)

Route::prefix('countries')->group(function () {
    Route::get('/', [LocationController::class, 'countries']);
    Route::get('/{id}', [LocationController::class, 'getCountryById']);
});

Route::prefix('states')->group(function () {
    Route::get('/', [LocationController::class, 'states']);
    Route::get('/{id}', [LocationController::class, 'getStateById']);
});

Route::prefix('cities')->group(function () {
    Route::get('/', [LocationController::class, 'cities']);
    Route::get('/{id}', [LocationController::class, 'getCityById']);
});

Route::get('/referral-sources', [LocationController::class, 'referralSources']);

// Public routes for plans (subscription options)
Route::prefix('plans')->group(function () {
    Route::get('/', [PlanController::class, 'index']);
    Route::get('/{id}', [PlanController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES - Requires auth:api Middleware
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/token/status', [AuthController::class, 'tokenStatus']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::prefix('profile')->group(function () {
        Route::get('/', [AuthController::class, 'profile']);
        Route::patch('/', [AuthController::class, 'updateProfile']);
        Route::delete('/', [AuthController::class, 'deleteAccount']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/user', [NotificationController::class, 'userNotifications']);
        Route::get('/unread', [NotificationController::class, 'unreadAnnouncements']);
    });

    Route::prefix('announcements')->group(function () {
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    });

    Route::prefix('notifications')->group(function () {
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });

    Route::prefix('stripe')->group(function () {
        Route::post('/customer-portal', [StripeSubscriptionController::class, 'createCustomerPortal']);
    });

    Route::prefix('subscription')->group(function () {
        Route::post('/refresh-role', [StripeSubscriptionController::class, 'refreshRole']);
        Route::get('/', [BillingController::class, 'current']);
        Route::get('/status', [BillingController::class, 'status']);
    });

    Route::prefix('billing')->group(function () {
        Route::get('/current', [BillingController::class, 'current']);
        Route::get('/history', [BillingController::class, 'history']);
    });

    // Basic announcement routes for all authenticated users
    Route::prefix('announcements')->group(function () {
        Route::get('/', [AnnouncementController::class, 'index']);
        Route::get('/{id}', [AnnouncementController::class, 'show']);
        Route::get('/scheduled/today', [AnnouncementController::class, 'todayScheduled']);
        Route::post('/date-range', [AnnouncementController::class, 'byDateRange']);
    });

    /*
    |----------------------------------------------------------------------
    | SUBSCRIPTION REQUIRED ROUTES
    |----------------------------------------------------------------------
    */

    Route::middleware('subscription.check')->group(function () {

        Route::apiResource('assessments', AssessmentController::class)->only(['index', 'store']);
        Route::get('/assessments-list', [AssessmentResponseController::class, 'getAssessments']);
        Route::post('/assessment-responses', [AssessmentResponseController::class, 'store']);
        Route::get('/assessment-responses', [AssessmentResponseController::class, 'getUserResponses']);
        Route::get('/assessment-attempts', [AssessmentResponseController::class, 'getUserAttempts']);
        Route::get('/assessment-timing', [AssessmentResponseController::class, 'getAssessmentTiming']);

        // Assessment Results - C++ Algorithm Integration
        Route::prefix('assessment-results')->group(function () {
            Route::post('/calculate', [AssessmentResultController::class, 'calculate']);
            Route::get('/user', [AssessmentResultController::class, 'getUserResults']);
            Route::get('/compare', [AssessmentResultController::class, 'compareResults']);
            Route::get('/{id}', [AssessmentResultController::class, 'show']);
        });
        Route::get('/assessment-system/status', [AssessmentResultController::class, 'checkSystemStatus']);

        // REMOVED: Legacy questions/answers/organization-assessment-questions routes
        // (controllers deleted - used non-existent tables: questions, answers, organization_assessment_questions)

        /* SUPERADMIN ONLY */
        Route::middleware('auth.role:superadmin')->group(function () {
            Route::apiResource('users', UserController::class);

            Route::prefix('users')->group(function () {
                Route::patch('/{user}/role', [UserController::class, 'updateRole']);
                Route::patch('/{user}/soft-delete', [UserController::class, 'softDelete']);
                Route::post('/{user}/impersonate', [UserController::class, 'impersonate']);
            });

            Route::prefix('organizations')->group(function () {
                Route::post('/', [OrganizationController::class, 'store']);
                Route::patch('/{organization}', [OrganizationController::class, 'update']);
                Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
            });

            // Superadmin announcement management
            Route::prefix('announcements')->group(function () {
                Route::post('/', [AnnouncementController::class, 'store']);
                Route::put('/{id}', [AnnouncementController::class, 'update']);
                Route::delete('/{id}', [AnnouncementController::class, 'destroy']);
            });

            // Plans management (superadmin only)
            Route::prefix('plans')->group(function () {
                Route::post('/', [PlanController::class, 'store']);
                Route::put('/{id}', [PlanController::class, 'update']);
                Route::delete('/{id}', [PlanController::class, 'destroy']);
            });

            // Webhook logs management (superadmin only)
            Route::prefix('webhook-logs')->group(function () {
                Route::get('/', [WebhookLogController::class, 'index']);
                Route::get('/unprocessed', [WebhookLogController::class, 'unprocessed']);
                Route::get('/{id}', [WebhookLogController::class, 'show']);
                Route::post('/', [WebhookLogController::class, 'store']);
                Route::put('/{id}', [WebhookLogController::class, 'update']);
                Route::delete('/{id}', [WebhookLogController::class, 'destroy']);
                Route::post('/{id}/mark-processed', [WebhookLogController::class, 'markAsProcessed']);
            });

            Route::prefix('announcements')->group(function () {
                Route::get('/', [NotificationController::class, 'allAnnouncements']);
                Route::get('/{id}', [NotificationController::class, 'showAnnouncement']);
            });

            Route::get('/notifications', [NotificationController::class, 'allNotifications']);
        });

        /* DOLPHIN ADMIN & SUPERADMIN */
        Route::middleware('auth.role:dolphinadmin,superadmin')->group(function () {
            Route::apiResource('leads', LeadController::class)->except(['index']);
            // Sending announcements is consolidated under POST /api/announcements
        });

        /* DOLPHIN ADMIN, SUPERADMIN & SALESPERSON */
        Route::get('/leads', [LeadController::class, 'index'])
            ->middleware('auth.role:dolphinadmin,superadmin,salesperson');

        /* ORGANIZATION ADMIN & SUPERADMIN */
        Route::middleware('auth.role:organizationadmin,superadmin')->group(function () {
            Route::prefix('organizations')->group(function () {
                Route::get('/', [OrganizationController::class, 'index']);
                Route::get('/{organization}', [OrganizationController::class, 'show']);
            });

            Route::prefix('groups')->group(function () {
                Route::get('/', [GroupController::class, 'index']);
                Route::get('/{group}', [GroupController::class, 'show']);
                Route::post('/{group}/add-member', [OrganizationUserController::class, 'addToGroup']);
                Route::post('/{group}/remove-member', [OrganizationUserController::class, 'removeFromGroup']);
                Route::post('/{group}/update-member-role', [OrganizationUserController::class, 'updateGroupRole']);
            });

            Route::prefix('organization')->group(function () {
                Route::get('/members', [OrganizationUserController::class, 'index']);
                Route::get('/members/available', [OrganizationUserController::class, 'availableUsers']);
                Route::post('/members/add', [OrganizationUserController::class, 'addOrganizationMember']);
                Route::post('/members/remove', [OrganizationUserController::class, 'removeOrganizationMember']);
                Route::get('/members/for-groups', [OrganizationUserController::class, 'getAvailableMembersForGroup']);
            });

            // REMOVED: Legacy members and member-roles routes
            // (controllers deleted - used non-existent tables: members, member_roles)
            // Use /organization/members endpoints instead
        });

        /* ORGANIZATION ADMIN ONLY */
        Route::middleware('auth.role:organizationadmin')->group(function () {
            Route::prefix('groups')->group(function () {
                Route::post('/', [GroupController::class, 'store']);
                Route::patch('/{group}', [GroupController::class, 'update']);
                Route::delete('/{group}', [GroupController::class, 'destroy']);
            });
        });

        /* DOLPHIN ADMIN, ORGANIZATION ADMIN & SUPERADMIN */
        Route::middleware('auth.role:dolphinadmin,organizationadmin,superadmin')->group(function () {
            // Lead notes management
            Route::prefix('leads/{leadId}/notes')->group(function () {
                Route::get('/', [LeadNoteController::class, 'index']);
                Route::post('/', [LeadNoteController::class, 'store']);
                Route::get('/{noteId}', [LeadNoteController::class, 'show']);
                Route::put('/{noteId}', [LeadNoteController::class, 'update']);
                Route::delete('/{noteId}', [LeadNoteController::class, 'destroy']);
            });
        });
    });
});
