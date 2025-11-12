<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class UserObserver
{
    
    public function deleted(User $user): void
    {
        $now = Carbon::now();
        try {
            
            $softDeleteTables = [
                'organizations',
                'groups',
                'subscriptions',
                'user_details',
            ];
            foreach ($softDeleteTables as $t) {
                if (DB::getSchemaBuilder()->hasTable($t)) {
                    DB::table($t)
                        ->where('user_id', $user->id)
                        ->update(['deleted_at' => $now]);
                }
            }

            
            $nullifyTables = [
                'answers',
                'assessments',
                'members',
                'sessions',
                'user_roles',
                'oauth_access_tokens',
                'oauth_auth_codes',
                'oauth_device_codes',
            ];
            foreach ($nullifyTables as $t) {
                if (DB::getSchemaBuilder()->hasTable($t) && DB::getSchemaBuilder()->hasColumn($t, 'user_id')) {
                    DB::table($t)->where('user_id', $user->id)->update(['user_id' => null]);
                }
            }
        } catch (\Exception $e) {
            
            Log::error('UserObserver deleted error: ' . $e->getMessage());
        }
    }

    
    public function restored(User $user): void
    {
        
        Log::info('User restored', ['user_id' => $user->getKey()]);
    }
}
