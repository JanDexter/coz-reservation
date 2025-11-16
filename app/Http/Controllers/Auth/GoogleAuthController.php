<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
        // Store intent (admin or customer) in session
        $intent = $request->query('intent', 'customer');
        session(['google_auth_intent' => $intent]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback()
    {
        try {
            // Use stateless in local to avoid state/session issues during OAuth callback
            $driver = Socialite::driver('google');
            if (app()->environment('local')) {
                $driver = $driver->stateless();
            }
            $googleUser = $driver->user();

            $intent = session('google_auth_intent', 'customer');
            session()->forget('google_auth_intent');

            $user = null;
            if ($intent === 'admin') {
                $user = $this->handleAdminLogin($googleUser);
            } else {
                $user = $this->handleCustomerLogin($googleUser);
            }

            if ($user) {
                Auth::login($user, true);
                // Regenerate session to prevent fixation and ensure cookie is refreshed
                request()->session()->regenerate();
                
                // Explicitly save the session before redirecting
                session()->save();

                // Log the successful login for debugging
                \Log::info('User ' . $user->id . ' successfully authenticated via Google.');

                // Redirect appropriately based on intent
                if ($intent === 'admin') {
                    return redirect()->intended(route('dashboard'))
                        ->with('success', 'Successfully signed in with Google.');
                }
                return redirect()->route('customer.view')
                    ->with('success', 'Successfully signed in with Google.');
            }

            return redirect()->route('customer.view')->with('error', 'Could not sign you in with Google.');

        } catch (\Exception $e) {
            \Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect()->route('customer.view')
                ->with('error', 'Failed to authenticate with Google. Please try again.');
        }
    }

    /**
     * Handle admin user login/creation
     */
    protected function handleAdminLogin($googleUser)
    {
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Update Google ID if not set
            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }

            // Check if user is active
            if (!$user->is_active) {
                return null; // Deactivated account
            }

            return $user;
        }

        return null; // No account found
    }

    /**
     * Handle customer login/creation for reservations
     */
    protected function handleCustomerLogin($googleUser)
    {
        // Find or create a User account for the customer
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name'              => $googleUser->getName() ?: trim(strtok($googleUser->getEmail(), '@')),
                'email'             => $googleUser->getEmail(),
                'password'          => \Illuminate\Support\Str::random(32), // hashed by casts
                'is_active'         => true,
                'email_verified_at' => now(),
                'google_id'         => $googleUser->getId(),
                'avatar'            => $googleUser->getAvatar(),
            ]);

            // Immediately create the customer profile for the new user
            Customer::create([
                'user_id' => $user->id,
                'name'    => $googleUser->getName() ?: trim(strtok($googleUser->getEmail(), '@')),
                'email'   => $googleUser->getEmail(),
            ]);

            // Refresh user to load the customer relationship
            $user->refresh();
        } else {
            // Keep user profile in sync
            $user->fill([
                'name'      => $user->name ?: ($googleUser->getName() ?: trim(strtok($googleUser->getEmail(), '@'))),
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar() ?: $user->avatar,
                'is_active' => true,
            ])->save();

            // Ensure a Customer profile exists and is linked to the User
            if (!$user->customer) {
                Customer::create([
                    'user_id' => $user->id,
                    'name'    => $googleUser->getName() ?: trim(strtok($googleUser->getEmail(), '@')),
                    'email'   => $googleUser->getEmail(),
                ]);
                $user->refresh();
            } else {
                // Check if customer was created by admin and needs email verification
                $customer = $user->customer;
                
                if ($customer->created_by_admin && !$customer->email_verified_at) {
                    // Generate verification URL
                    $verificationUrl = URL::temporarySignedRoute(
                        'customer.verify-email',
                        now()->addHours(24),
                        ['customer' => $customer->id]
                    );
                    
                    // Send verification email
                    try {
                        Mail::send('emails.customer-verification', [
                            'customer' => $customer,
                            'verificationUrl' => $verificationUrl
                        ], function ($message) use ($customer) {
                            $message->to($customer->email)
                                    ->subject('Verify Your CO-Z Co-Workspace Account');
                        });
                        
                        \Log::info('Verification email sent to customer: ' . $customer->email);
                    } catch (\Exception $e) {
                        \Log::error('Failed to send verification email: ' . $e->getMessage());
                    }
                    
                    // Prevent login - return null to deny access
                    session()->flash('status', 'A verification email has been sent to ' . $customer->email . '. Please verify your email to access your account.');
                    return null;
                }
            }
        }

        return $user; // Return Authenticatable User for session login
    }

    /**
     * Handle two-factor requirements after a successful admin login.
     */
    protected function handleTwoFactorRedirect($user)
    {
        $session = session();

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            $session->put('two_factor:id', $user->id);
            $session->put('two_factor:remember', true);
            $session->put('two_factor:intended', route('dashboard'));

            Auth::logout();

            $session->flash('status', 'Please complete two-factor authentication to continue.');

            $session->regenerate();

            return redirect()->route('two-factor.challenge');
        }

        $session->regenerate();

        return redirect()->intended(route('dashboard'));
    }
    
    /**
     * Verify customer email from verification link
     */
    public function verifyCustomerEmail(Customer $customer)
    {
        if ($customer->email_verified_at) {
            return redirect()->route('customer.view')
                ->with('success', 'Your email is already verified. You can now sign in.');
        }
        
        // Mark email as verified and activate account
        $customer->update([
            'email_verified_at' => now(),
            'status' => 'active'
        ]);
        
        \Log::info('Customer email verified: ' . $customer->email);
        
        return redirect()->route('customer.view')
            ->with('success', 'Email verified successfully! Your account is now active. Please sign in with Google to continue.');
    }
}
