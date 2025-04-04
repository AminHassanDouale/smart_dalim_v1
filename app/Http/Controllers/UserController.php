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
     * Display a listing of the users.
     *
     * @return \Illuminate\Http\Response
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
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        try {
            $result = $this->d1Service->createUser($validated);
            return redirect()->route('users.index')->with('success', 'User created successfully');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        try {
            $result = $this->d1Service->updateUser($id, $validated);
            return redirect()->route('users.index')->with('success', 'User updated successfully');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $result = $this->d1Service->deleteUser($id);
            return redirect()->route('users.index')->with('success', 'User deleted successfully');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}