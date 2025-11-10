<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Group;
use App\Models\Organization;
use App\Notifications\GeneralNotification;
use App\Notifications\NewAnnouncement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AnnouncementRead;

class NotificationController extends Controller
{
    // Return unread announcements for the authenticated user
    public function unreadAnnouncements(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        // The project does not use the Laravel `notifications` table in this
        // database. Instead announcements are stored in the `announcements`
        // and related pivot tables (`announcement_organizations`,
        // `announcement_groups`, `announcement_dolphin_admins`).
        //
        // Since there is no per-user persistent "read" tracking in the
        // existing schema, we treat all announcements addressed to the user
        // as "unread" (i.e. return all relevant announcements). This avoids
        // relying on a missing `notifications` table and keeps the API
        // functional for the frontend.

        $userId = $user->id;
        $orgId = $user->organization_id;
        $groupIds = $user->groups()->pluck('groups.id')->toArray();

        $announcements = Announcement::where(function ($q) use ($userId, $orgId, $groupIds) {
            // Announcements sent explicitly to this admin
            $q->whereHas('admins', function ($q2) use ($userId) {
                $q2->where('users.id', $userId);
            });

            // Announcements targeted to the user's organization
            if (!empty($orgId)) {
                $q->orWhereHas('organizations', function ($q2) use ($orgId) {
                    $q2->where('organizations.id', $orgId);
                });
            }

            // Announcements targeted to groups the user belongs to
            if (!empty($groupIds)) {
                $q->orWhereHas('groups', function ($q2) use ($groupIds) {
                    $q2->whereIn('groups.id', $groupIds);
                });
            }

            // Also check announcement_groups.member_ids JSON for direct user ids
            $q->orWhereExists(function ($sub) use ($userId) {
                $sub->select(DB::raw(1))
                    ->from('announcement_groups')
                    ->whereColumn('announcement_groups.announcement_id', 'announcements.id')
                    ->whereRaw('JSON_CONTAINS(announcement_groups.member_ids, ?)', [json_encode((string) $userId)]);
            });
        })->with(['organizations', 'groups', 'admins'])->orderByDesc('created_at')->get();

        // Exclude announcements that the user has already read (stored in announcement_reads)
        $announcements = $announcements->filter(function ($a) use ($userId) {
            $exists = AnnouncementRead::where('announcement_id', $a->id)->where('user_id', $userId)->exists();
            return !$exists;
        })->values();

        // Normalize shape expected by frontend (some clients expect `body` and scheduled_at)
        $announcements->transform(function ($a) {
            $a->body = $a->message ?? $a->body ?? null;
            return $a;
        });

        return response()->json(['unread' => $announcements]);
    }

    // Adapter for frontend: GET /api/notifications (all)
    public function allNotifications(Request $request)
    {
        try {
            // Allow callers to request notifications for a specific notifiable.
            // If not provided, and a user is authenticated, default to that user.
            $notifiableType = $request->input('notifiable_type');
            $notifiableId = $request->input('notifiable_id');

            if (!$notifiableType || !$notifiableId) {
                $user = $request->user();
                if ($user) {
                    $notifiableType = 'App\\Models\\User';
                    $notifiableId = $user->id;
                } else {
                    // No filter provided and no authenticated user: avoid returning
                    // the entire notifications table; require a notifiable filter.
                    return response()->json(['error' => 'notifiable_type and notifiable_id required'], 400);
                }
            }

            // The legacy `notifications` table is not available. Provide a
            // best-effort mapping from the announcement tables based on the
            // requested notifiable filter. For users, return announcements
            // addressed to that user. For organizations, return announcements
            // for that organization. Other types are not supported by this
            // adapter.

            if ($notifiableType === 'App\\Models\\User') {
                $user = User::find($notifiableId);
                if (!$user) {
                    return response()->json(['notifications' => []]);
                }
                // Reuse logic from unreadAnnouncements but for the supplied id
                $groupIds = $user->groups()->pluck('groups.id')->toArray();
                $orgId = $user->organization_id;

                $notifications = Announcement::where(function ($q) use ($notifiableId, $orgId, $groupIds) {
                    $q->whereHas('admins', function ($q2) use ($notifiableId) {
                        $q2->where('users.id', $notifiableId);
                    });

                    if (!empty($orgId)) {
                        $q->orWhereHas('organizations', function ($q2) use ($orgId) {
                            $q2->where('organizations.id', $orgId);
                        });
                    }

                    if (!empty($groupIds)) {
                        $q->orWhereHas('groups', function ($q2) use ($groupIds) {
                            $q2->whereIn('groups.id', $groupIds);
                        });
                    }

                    $q->orWhereExists(function ($sub) use ($notifiableId) {
                        $sub->select(DB::raw(1))
                            ->from('announcement_groups')
                            ->whereColumn('announcement_groups.announcement_id', 'announcements.id')
                            ->whereRaw('JSON_CONTAINS(announcement_groups.member_ids, ?)', [json_encode((string) $notifiableId)]);
                    });
                })->with(['organizations', 'groups', 'admins'])->orderByDesc('created_at')->get();

                return response()->json(['notifications' => $notifications]);
            }

            if ($notifiableType === 'App\\Models\\Organization') {
                $notifications = Announcement::whereHas('organizations', function ($q) use ($notifiableId) {
                    $q->where('organizations.id', $notifiableId);
                })->with(['organizations', 'groups', 'admins'])->orderByDesc('created_at')->get();

                return response()->json(['notifications' => $notifications]);
            }

            return response()->json(['error' => 'Unsupported notifiable_type for announcements adapter'], 400);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch notifications'], 500);
        }
    }

    // Adapter for frontend: GET /api/notifications/user (for authenticated user's notifications)
    public function userNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            // Provide announcements relevant to the authenticated user.
            $userId = $user->id;
            $orgId = $user->organization_id;
            $groupIds = $user->groups()->pluck('groups.id')->toArray();

            $notifications = Announcement::where(function ($q) use ($userId, $orgId, $groupIds) {
                $q->whereHas('admins', function ($q2) use ($userId) {
                    $q2->where('users.id', $userId);
                });

                if (!empty($orgId)) {
                    $q->orWhereHas('organizations', function ($q2) use ($orgId) {
                        $q2->where('organizations.id', $orgId);
                    });
                }

                if (!empty($groupIds)) {
                    $q->orWhereHas('groups', function ($q2) use ($groupIds) {
                        $q2->whereIn('groups.id', $groupIds);
                    });
                }

                $q->orWhereExists(function ($sub) use ($userId) {
                    $sub->select(DB::raw(1))
                        ->from('announcement_groups')
                        ->whereColumn('announcement_groups.announcement_id', 'announcements.id')
                        ->whereRaw('JSON_CONTAINS(announcement_groups.member_ids, ?)', [json_encode((string) $userId)]);
                });
            })->with(['organizations', 'groups', 'admins'])->orderByDesc('created_at')->get();

            return response()->json(['notifications' => $notifications]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user notifications', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch user notifications'], 500);
        }
    }
    // Return all announcements (for superadmin or testing)
    // Public: Return all announcements (no auth required)
    public function allAnnouncements()
    {
        $announcements = Announcement::orderByDesc('created_at')->get();
        return response()->json($announcements);
    }

    // Return a single announcement with related pivot data (organizations, groups, admins, members)

    public function showAnnouncement($id)
    {
        try {
            $announcement = Announcement::with(['organizations', 'groups', 'admins'])->select()->findOrFail($id);

            $data = [
                'id' => $announcement->id,
                'body' => $announcement->body,
                // sender_id and sent_at are not stored on the announcements table
                // (schema only contains message/schedule_date/schedule_time). Expose
                // null explicitly to keep response shape stable for frontend clients.
                'sender_id' => $announcement->sender_id ?? null,
                'scheduled_at' => $announcement->scheduled_at,
                'sent_at' => $announcement->sent_at ?? null,
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
                'organizations' => $announcement->organizations->map(fn($org) => [
                    'id' => $org->id,
                    // organizations table uses `name` column
                    'name' => $org->name,
                    'contact_email' => $org->user?->email ?? null,
                    'user_id' => $org->user_id,
                    'user_first_name' => $org->user?->first_name ?? null,
                    'user_last_name' => $org->user?->last_name ?? null,
                ]),
                'groups' => $announcement->groups->map(fn($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'organization_id' => $g->organization_id,
                    'organization_name' => $g->organization?->name ?? null,
                    'org_contact_email' => $g->organization?->user?->email ?? null,
                ]),
                'admins' => $announcement->admins->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->first_name . ' ' . $a->last_name,
                    'email' => $a->email,
                ]),
            ];

            // Try to read legacy `notifications` table rows related to this announcement
            $notifRowsQuery = DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->whereRaw("JSON_EXTRACT(data, '$.announcement_id') = ?", [$announcement->id]);

            try {
                $notifRows = $notifRowsQuery->get();
            } catch (\Throwable $e) {
                Log::warning('[showAnnouncement] notifications JSON_EXTRACT query failed', ['announcement_id' => $announcement->id, 'error' => $e->getMessage()]);
                $notifRows = collect();
            }

            // Also include reads persisted via announcement_reads so the UI can
            // display per-user read timestamps even if the legacy `notifications`
            // table is not used.
            try {
                $announcementReads = AnnouncementRead::where('announcement_id', $announcement->id)->get();
            } catch (\Throwable $e) {
                Log::warning('[showAnnouncement] failed to fetch announcement_reads', ['announcement_id' => $announcement->id, 'error' => $e->getMessage()]);
                $announcementReads = collect();
            }

            // Map announcement_reads into the same shape as notification rows
            $readRows = $announcementReads->map(function ($r) {
                return (object) [
                    'notifiable_id' => $r->user_id,
                    'read_at' => $r->read_at,
                    'data' => json_encode(['announcement_id' => $r->announcement_id]),
                ];
            });

            // Merge both sources so frontend receives a single notifications array
            $notifRows = collect($notifRows)->merge($readRows);

            $readUserMap = [];
            foreach ($notifRows as $nr) {
                if (!empty($nr->read_at)) {
                    $readUserMap[$nr->notifiable_id] = true;
                }
            }

            $orgMap = [];
            foreach ($announcement->organizations as $o) {
                $orgMap[$o->id] = $o;
            }


            if (empty($orgMap)) {
                try {
                    $orgIds = $announcement->groups->pluck('organization_id')->filter()->unique()->values()->toArray();
                    if (!empty($orgIds)) {
                        $fetchedOrgs = Organization::whereIn('id', $orgIds)->get();
                        foreach ($fetchedOrgs as $fo) {
                            $orgMap[$fo->id] = $fo;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('[showAnnouncement] failed to fetch referenced organizations', ['announcement_id' => $announcement->id, 'error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'announcement' => $data,
                'notifications' => $notifRows,
            ]);
        } catch (\Exception $e) {
            Log::error('[showAnnouncement] error', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Announcement not found'], 404);
        }
    }

    // send() deprecated: logic moved to AnnouncementController@store

    // getRecipientsForAnnouncement() removed; now handled in AnnouncementController

    // userAnnouncements() removed: functionality overlaps with userNotifications() and
    // frontend routes currently use `userNotifications`. Remove to simplify controller.
    // Mark a notification as read for the authenticated user
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $announcement = Announcement::findOrFail($id);

            // Ensure the user is one of the intended recipients (admins/org/groups)
            // For now allow marking read for announcements the user can see via unreadAnnouncements logic.

            AnnouncementRead::updateOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => $user->id],
                ['read_at' => now()]
            );

            Log::info('Announcement marked as read', ['announcement_id' => $announcement->id, 'user_id' => $user->id]);

            return response()->json(['message' => 'Marked as read']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Announcement not found'], 404);
        } catch (\Exception $e) {
            Log::error('Failed to mark announcement as read', ['announcement_id' => $id, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to mark as read'], 500);
        }
    }

    // Mark all notifications as read for the authenticated user
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            // Reuse the logic that finds announcements for the user (similar to userNotifications)
            $userId = $user->id;
            $orgId = $user->organization_id;
            $groupIds = $user->groups()->pluck('groups.id')->toArray();

            $announcements = Announcement::where(function ($q) use ($userId, $orgId, $groupIds) {
                $q->whereHas('admins', function ($q2) use ($userId) {
                    $q2->where('users.id', $userId);
                });

                if (!empty($orgId)) {
                    $q->orWhereHas('organizations', function ($q2) use ($orgId) {
                        $q2->where('organizations.id', $orgId);
                    });
                }

                if (!empty($groupIds)) {
                    $q->orWhereHas('groups', function ($q2) use ($groupIds) {
                        $q2->whereIn('groups.id', $groupIds);
                    });
                }

                $q->orWhereExists(function ($sub) use ($userId) {
                    $sub->select(DB::raw(1))
                        ->from('announcement_groups')
                        ->whereColumn('announcement_groups.announcement_id', 'announcements.id')
                        ->whereRaw('JSON_CONTAINS(announcement_groups.member_ids, ?)', [json_encode((string) $userId)]);
                });
            })->pluck('id');

            foreach ($announcements as $aid) {
                AnnouncementRead::updateOrCreate(['announcement_id' => $aid, 'user_id' => $userId], ['read_at' => now()]);
            }

            Log::info('Marked all announcements as read for user', ['user_id' => $userId, 'count' => count($announcements)]);

            return response()->json(['message' => 'All marked as read']);
        } catch (\Exception $e) {
            Log::warning('Failed to mark all notifications as read', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to mark all as read'], 500);
        }
    }

    // Manual API endpoint to create a notification record (for testing)
    public function createNotification(Request $request)
    {
        // Validation omitted: this endpoint currently returns 501 because
        // creating ad-hoc notification records is not supported by the
        // current schema. Keep the method shape but avoid validating unused data.

        try {
            // Creating a record in the `notifications` table is not possible
            // since that table is not present. If the goal is to create an
            // announcement, use the announcements endpoints instead. Return a
            // clear 501 so callers can adapt.
            return response()->json(['error' => 'Creating ad-hoc notification records is not supported; use announcements API'], 501);
        } catch (\Exception $e) {
            Log::error('[createNotification] failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create notification'], 500);
        }
    }
}
