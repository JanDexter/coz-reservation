<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::query()->with(['admin', 'staff', 'customer']);

        // Filter by role if provided
        if ($request->filled('role')) {
            switch ($request->role) {
                case 'admin':
                    $query->whereHas('admin');
                    break;
                case 'staff':
                    $query->whereHas('staff');
                    break;
                case 'customer':
                    $query->whereHas('customer');
                    break;
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        // Transform users to include role_type
        $users->getCollection()->transform(function ($user) {
            $userData = $user->toArray();
            $userData['role_type'] = $user->isAdmin() ? 'admin' : ($user->isStaff() ? 'staff' : 'customer');
            return $userData;
        });

        // Get user statistics
        $stats = [
            'total_users' => User::count(),
            'admin_users' => User::whereHas('admin')->count(),
            'staff_users' => User::whereHas('staff')->count(),
            'customer_users' => User::whereHas('customer')->count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
        ];

        return Inertia::render('UserManagement/Index', [
            'users' => $users,
            'stats' => $stats,
            'filters' => $request->only(['role', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return Inertia::render('UserManagement/Create');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email', // Unique email validation enforced
            'phone' => ['nullable', 'string', 'regex:/^(\+63\d{10}|09\d{9})$/'],
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['customer', 'staff', 'admin'])],
            'is_active' => 'boolean',
        ], [
            'phone.regex' => 'Invalid phone number format. Please use +639XXXXXXXXX or 09XXXXXXXXX format.',
        ]);

        DB::beginTransaction();
        try {
            // Create the base user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => bcrypt($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
                'email_verified_at' => now(), // Email verification disabled
            ]);

            // Create the appropriate role record
            switch ($validated['role']) {
                case 'admin':
                    Admin::create([
                        'user_id' => $user->id,
                        'permission_level' => 'admin', // Default to admin, not super_admin
                    ]);
                    break;
                case 'staff':
                    Staff::create([
                        'user_id' => $user->id,
                        'position' => 'Staff',
                    ]);
                    break;
                case 'customer':
                    Customer::create([
                        'user_id' => $user->id,
                        'company_name' => $validated['name'], // Use user name as company name
                        'contact_person' => $validated['name'],
                        'email' => $validated['email'],
                        'phone' => $validated['phone'] ?? null,
                        'status' => 'active',
                    ]);
                    break;
            }

            DB::commit();
            return redirect()->route('user-management.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        // Load reservations with relationships like Dashboard does
        $reservations = \App\Models\Reservation::with(['customer', 'space.spaceType'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['completed', 'paid', 'partial', 'pending', 'active'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($reservation) {
                $cost = $reservation->is_open_time ? $reservation->cost : ($reservation->cost ?? $reservation->total_cost);
                return [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer->company_name ?? $reservation->customer->name ?? 'N/A',
                    'space_name' => $reservation->space->name ?? 'N/A',
                    'space_type' => $reservation->space->spaceType->name ?? 'N/A',
                    'start_time' => $reservation->start_time,
                    'end_time' => $reservation->end_time,
                    'cost' => $cost,
                    'total_cost' => $reservation->total_cost,
                    'amount_paid' => $reservation->amount_paid ?? 0,
                    'status' => $reservation->status,
                    'payment_method' => $reservation->payment_method,
                    'is_open_time' => $reservation->is_open_time,
                ];
            });

        // Use the total_cost from mapped reservations
        $totalSpent = $reservations->sum('total_cost');
        $points = floor($totalSpent / 10); // Example: 1 point for every $10 spent

        // Add role information to user
        $userData = $user->toArray();
        $userData['is_admin'] = $user->isAdmin();
        $userData['is_staff'] = $user->isStaff();
        $userData['is_customer'] = $user->isCustomer();
        $userData['role_type'] = $user->isAdmin() ? 'admin' : ($user->isStaff() ? 'staff' : 'customer');
        
        // Load admin details if admin
        if ($user->isAdmin()) {
            $userData['admin'] = $user->admin;
        }

        return Inertia::render('UserManagement/Show', [
            'user' => $userData,
            'reservations' => $reservations,
            'totalSpent' => $totalSpent,
            'points' => $points,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $userData = $user->toArray();
        $userData['role_type'] = $user->isAdmin() ? 'admin' : ($user->isStaff() ? 'staff' : 'customer');
        
        return Inertia::render('UserManagement/Edit', [
            'user' => $userData,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)], // Ignore current user's email
            'phone' => ['nullable', 'string', 'regex:/^(\+63\d{10}|09\d{9})$/'],
            'role' => ['required', Rule::in(['customer', 'staff', 'admin'])],
            'is_active' => 'boolean',
        ], [
            'phone.regex' => 'Invalid phone number format. Please use +639XXXXXXXXX or 09XXXXXXXXX format.',
        ]);

        DB::beginTransaction();
        try {
            // Check if role is changing
            $currentRole = $user->isAdmin() ? 'admin' : ($user->isStaff() ? 'staff' : 'customer');
            $isRoleChanging = $currentRole !== $validated['role'];

            // Update base user fields
            $userUpdateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? $user->is_active,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $request->validate([
                    'password' => 'required|string|min:8|confirmed',
                ]);
                $userUpdateData['password'] = bcrypt($request->password);
            }

            // Email verification disabled - ensure email_verified_at is set if changing email
            if ($request->filled('email') && $request->email !== $user->email) {
                $userUpdateData['email_verified_at'] = now();
            }

            $user->update($userUpdateData);

            // Handle role change if needed
            if ($isRoleChanging) {
                // Delete old role records
                if ($user->admin) {
                    $user->admin()->delete();
                }
                if ($user->staff) {
                    $user->staff()->delete();
                }
                if ($user->customer) {
                    $user->customer()->delete();
                }

                // Create new role record
                switch ($validated['role']) {
                    case 'admin':
                        Admin::create([
                            'user_id' => $user->id,
                            'permission_level' => 'admin',
                        ]);
                        break;
                    case 'staff':
                        Staff::create([
                            'user_id' => $user->id,
                            'position' => 'Staff',
                        ]);
                        break;
                    case 'customer':
                        Customer::create([
                            'user_id' => $user->id,
                        ]);
                        break;
                }
            }

            DB::commit();
            return redirect()->route('user-management.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Toggle user status (activate/deactivate).
     */
    public function toggleStatus(User $user)
    {
        // Prevent deactivating the sole admin
        if ($user->isAdmin() && $user->is_active) {
            return redirect()->back()->withErrors(['status' => 'The single admin account cannot be deactivated.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "User {$status} successfully.");
    }
}
