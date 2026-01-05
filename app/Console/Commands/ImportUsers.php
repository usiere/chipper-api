<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

class ImportUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users {url} {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from a JSON URL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->argument('url');
        $limit = (int) $this->option('limit');

        if (!$limit) {
            $this->error('Limit parameter is required');
            return Command::FAILURE;
        }

        try {
            $response = Http::get($url);

            if (!$response->successful()) {
                $this->error('Failed to fetch data from URL');
                return Command::FAILURE;
            }

            $users = collect($response->json())->take($limit);

            $imported = 0;
            foreach ($users as $userData) {
                // Skip if email already exists
                if (User::where('email', $userData['email'])->exists()) {
                    $this->warn("Skipping {$userData['email']} - already exists");
                    continue;
                }

                User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'), // Default password
                ]);

                $imported++;
            }

            $this->info("Successfully imported {$imported} users");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
