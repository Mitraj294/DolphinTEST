<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder; // Make sure DB facade is imported
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first
        $this->call(RolesSeeder::class);

        // Create superadmin user
        if (!User::where('email', 'sdolphin632@gmail.com')->exists()) {
            $user = User::create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'sdolphin632@gmail.com',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]);

            $superadminRole = Role::where('name', 'superadmin')->first();
            if ($superadminRole) {
                $user->roles()->attach($superadminRole->id);
            }
        }

        // Seed member roles (if seeder exists)
        if (class_exists(\Database\Seeders\MemberRoleSeeder::class)) {
            $this->call(MemberRoleSeeder::class);
        }

        // Seed organization assessment questions (if seeder exists)
        if (class_exists(\Database\Seeders\OrganizationAssessmentQuestionSeeder::class)) {
            $this->call(\Database\Seeders\OrganizationAssessmentQuestionSeeder::class);
        }
        // Assign 'user' role to all users who don't have any roles
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $users = User::all();
            foreach ($users as $user) {
                if ($user->roles()->count() === 0) {
                    $user->roles()->attach($userRole->id);
                }
            }
        }

        // assessment questions (if questions table exists)
        if (DB::getSchemaBuilder()->hasTable('questions')) {
            $options = [
                'Relaxed',
                'Persuasive',
                'Stable',
                'Charismatic',
                'Individualistic',
                'Optimistic',
                'Conforming',
                'Methodical',
                'Serious',
                'Friendly',
                'Humble',
                'Unrestrained',
                'Competitive',
                'Docile',
                'Restless',
            ];

            if (DB::table('questions')->count() === 0) {
                DB::table('questions')->insert([
                    [
                        'question' => 'Q.1 Please select the words below that describe how other people expect you to be in most daily situations.',
                        'options' => json_encode($options),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'question' => 'Q.2 Please select the words below that describe how you really are, not how you are expected to be.',
                        'options' => json_encode($options),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }

        // Optionally create a sample lead (uncomment if needed)
        // Lead::create([
        //     'first_name' => 'John',
        //     'last_name' => 'Doe',
        //     'email' => 'john.doe@example.com',
        //     'phone_number' => '1234567890',
        //     'status' => 'Lead Stage',
        // ]);
    }
}
