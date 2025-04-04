<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class CloudflareD1Service
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
    }

    /**
     * Get all users
     *
     * @return array
     * @throws Exception
     */
    public function getAllUsers()
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/users");

            if ($response->successful()) {
                return $response->json()['data'];
            }

            throw new Exception($response->json()['error'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            throw new Exception('Failed to fetch users: ' . $e->getMessage());
        }
    }

    /**
     * Get a user by ID
     *
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function getUserById($id)
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/users/{$id}");

            if ($response->successful()) {
                return $response->json()['data'];
            }

            throw new Exception($response->json()['error'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            throw new Exception('Failed to fetch user: ' . $e->getMessage());
        }
    }

    /**
     * Create a new user
     *
     * @param array $userData
     * @return array
     * @throws Exception
     */
    public function createUser($userData)
    {
        try {
            $response = Http::post("{$this->baseUrl}/api/users", $userData);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception($response->json()['error'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            throw new Exception('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Update a user
     *
     * @param int $id
     * @param array $userData
     * @return array
     * @throws Exception
     */
    public function updateUser($id, $userData)
    {
        try {
            $response = Http::put("{$this->baseUrl}/api/users/{$id}", $userData);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception($response->json()['error'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            throw new Exception('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Delete a user
     *
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function deleteUser($id)
    {
        try {
            $response = Http::delete("{$this->baseUrl}/api/users/{$id}");

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception($response->json()['error'] ?? 'Unknown error occurred');
        } catch (Exception $e) {
            throw new Exception('Failed to delete user: ' . $e->getMessage());
        }
    }

    // Add more methods for other entities as needed
}