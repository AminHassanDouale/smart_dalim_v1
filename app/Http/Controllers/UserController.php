<?php

namespace App\Http\Controllers;

use App\Services\CloudflareD1Service;
use Illuminate\Http\Request;
use Exception;

class UserController extends Controller
{
    protected $d1Service;

    public function __construct(CloudflareD1Service $d1Service)
    {
        $this->d1Service = $d1Service;
    }

    /**
     * Display a listing of users.
     */
    public function index()
    {
        try {
            $users = $this->d1Service->getAllUsers();
            return view('users.index', compact('users'));
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        try {
            $this->d1Service->createUser($request->only(['name', 'email']));
            return redirect()->route('users.index')->with('success', 'User created successfully!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        try {
            $user = $this->d1Service->getUserById($id);
            return view('users.show', compact('user'));
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit($id)
    {
        try {
            $user = $this->d1Service->getUserById($id);
            return view('users.edit', compact('user'));
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        try {
            $this->d1Service->updateUser($id, $request->only(['name', 'email']));
            return redirect()->route('users.index')->with('success', 'User updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        try {
            $this->d1Service->deleteUser($id);
            return redirect()->route('users.index')->with('success', 'User deleted successfully!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
