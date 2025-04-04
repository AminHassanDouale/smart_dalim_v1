<?php

// Create a new controller: app/Http/Controllers/Api/CloudflareProxyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CloudflareProxyController extends Controller
{
    protected $workerUrl;

    public function __construct()
    {
        $this->workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
    }

    public function getUsers()
    {
        $response = Http::get("{$this->workerUrl}/api/users");
        return $response->json();
    }

    public function getUser($id)
    {
        $response = Http::get("{$this->workerUrl}/api/users/{$id}");
        return $response->json();
    }

    public function createUser(Request $request)
    {
        $response = Http::post("{$this->workerUrl}/api/users", $request->all());
        return $response->json();
    }

    public function updateUser(Request $request, $id)
    {
        $response = Http::put("{$this->workerUrl}/api/users/{$id}", $request->all());
        return $response->json();
    }

    public function deleteUser($id)
    {
        $response = Http::delete("{$this->workerUrl}/api/users/{$id}");
        return $response->json();
    }
}