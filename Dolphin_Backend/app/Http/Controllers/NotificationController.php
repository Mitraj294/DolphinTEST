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
    
    public function unreadAnnouncements(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        
        
        
        
        
        
        
        
        

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
        })->with(['organizations', 'groups', 'admins'])->orderByDesc('created_at')->get();

        
        $announcements = $announcements->filter(function ($a) use ($userId) {
            $exists = AnnouncementRead::where('announcement_id', $a->id)->where('user_id', $userId)->exists();
            return !$exists;
        })->values();

        
        $announcements->transform(function ($a) {
            $a->body = $a->message ?? $a->body ?? null;
            return $a;
        });

        return response()->json(['unread' => $announcements]);
    }

    
    public function allNotifications(Request $request)
    {
        try {
            
            
            $notifiableType = $request->input('notifiable_type');
            $notifiableId = $request->input('notifiable_id');

            if (!$notifiableType || !$notifiableId) {
                $user = $request->user();
                if ($user) {
                    $notifiableType = 'App\\Models\\User';
                    $notifiableId = $user->id;
                } else {
                    
                    
                    return response()->json(['error' => 'notifiable_type and notifiable_id required'], 400);
                }
            }

            
            
            
            
            
            

            if ($notifiableType === 'App\\Models\\User') {
                $user = User::find($notifiableId);
                if (!$user) {
                    return response()->json(['notifications' => []]);
                }
                
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

    
    public function userNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            
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
    
    
    public function allAnnouncements()
    {
        $announcements = Announcement::orderByDesc('created_at')->get();
        return response()->json($announcements);
    }

    

    public function showAnnouncement($id)
    {
        try {
            $announcement = Announcement::with(['organizations', 'groups', 'admins'])->select()->findOrFail($id);

            $data = [
                'id' => $announcement->id,
                'body' => $announcement->body,
                
                
                
                'sender_id' => $announcement->sender_id ?? null,
                'scheduled_at' => $announcement->scheduled_at,
                'sent_at' => $announcement->sent_at ?? null,
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
                'organizations' => $announcement->organizations->map(fn($org) => [
                    'id' => $org->id,
                    
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

            
            $notifRowsQuery = DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->whereRaw("JSON_EXTRACT(data, '$.announcement_id') = ?", [$announcement->id]);

            try {
                $notifRows = $notifRowsQuery->get();
            } catch (\Throwable $e) {
                Log::warning('[showAnnouncement] notifications JSON_EXTRACT query failed', ['announcement_id' => $announcement->id, 'error' => $e->getMessage()]);
                $notifRows = collect();
            }

            
            
            
            try {
                $announcementReads = AnnouncementRead::where('announcement_id', $announcement->id)->get();
            } catch (\Throwable $e) {
                Log::warning('[showAnnouncement] failed to fetch announcement_reads', ['announcement_id' => $announcement->id, 'error' => $e->getMessage()]);
                $announcementReads = collect();
            }

            
            $readRows = $announcementReads->map(function ($r) {
                return (object) [
                    'notifiable_id' => $r->user_id,
                    'read_at' => $r->read_at,
                    'data' => json_encode(['announcement_id' => $r->announcement_id]),
                ];
            });

            
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

    

    

    
    
    
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            $announcement = Announcement::findOrFail($id);

            
            

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

    
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        try {
            
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

    
    public function createNotification(Request $request)
    {
        
        
        

        try {
            
            
            
            
            return response()->json(['error' => 'Creating ad-hoc notification records is not supported; use announcements API'], 501);
        } catch (\Exception $e) {
            Log::error('[createNotification] failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create notification'], 500);
        }
    }
}
