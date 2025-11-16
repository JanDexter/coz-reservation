# Error Pages Fix for Production

## Problem
Error pages (404, 500, etc.) were not displaying on the production deployment at https://www.cozcoworkspace.page/

## Root Cause
The error handler in `bootstrap/app.php` was only handling Inertia requests but wasn't properly detecting them. In production with `APP_DEBUG=false`, Laravel needs to handle both:
1. **Inertia requests** (from Vue SPA navigation) - Should return Inertia responses
2. **Regular HTTP requests** (direct URL access, search engine crawlers) - Should return Blade views

## Solution Applied

### Updated `bootstrap/app.php` Error Handler
The error handler now:
- ✅ Detects Inertia requests using the `X-Inertia` header
- ✅ Returns Inertia responses for SPA navigation (Errors/404.vue, Errors/Error.vue)
- ✅ Returns Blade views for direct HTTP requests (errors/404.blade.php, errors/500.blade.php)
- ✅ Properly handles 404, 403, 500, 503 error codes

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->respond(function ($response, $exception, $request) {
        $statusCode = $response->getStatusCode();

        // Handle 404 errors
        if ($statusCode === 404) {
            // Inertia SPA request
            if ($request->header('X-Inertia')) {
                return inertia('Errors/404', ['status' => 404])
                    ->toResponse($request)
                    ->setStatusCode(404);
            }
            // Regular HTTP request
            return response()->view('errors.404', ['exception' => $exception], 404);
        }

        // Handle other errors
        if (in_array($statusCode, [500, 503, 403])) {
            // Inertia SPA request
            if ($request->header('X-Inertia')) {
                return inertia('Errors/Error', ['status' => $statusCode])
                    ->toResponse($request)
                    ->setStatusCode($statusCode);
            }
            // Regular HTTP request
            $viewName = view()->exists("errors.{$statusCode}") ? "errors.{$statusCode}" : 'errors.500';
            return response()->view($viewName, ['exception' => $exception], $statusCode);
        }

        return $response;
    });
})
```

## Deployment Steps

### 1. On Your Production Server (Google Cloud VM)

```bash
# Navigate to project
cd /path/to/your/admin

# Pull latest changes (includes error handler fix)
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Build production assets
npm install
npm run build

# Set proper permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm  # Adjust PHP version if needed
sudo systemctl restart nginx
```

### 2. Verify Production .env Settings

Ensure your production `.env` has:
```env
APP_ENV=production
APP_DEBUG=false          # ⚠️ MUST BE FALSE
APP_URL=https://www.cozcoworkspace.page
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

### 3. Test Error Pages

After deployment, test all error pages work:

**Test 404 Error:**
- Visit: https://www.cozcoworkspace.page/this-does-not-exist
- Expected: Should show custom 404 page (Blade view for direct access, Inertia page for SPA navigation)

**Test within SPA:**
- Login to admin panel
- Try navigating to non-existent route in SPA
- Expected: Should show Vue error page without full page reload

**Test 500 Error (optional, be careful):**
- Temporarily add a test route that throws an exception
- Expected: Should show custom error page

## Files Involved

### Error Views (Both formats needed)
- **Blade Templates** (for direct HTTP requests):
  - `resources/views/errors/404.blade.php`
  - `resources/views/errors/403.blade.php`
  - `resources/views/errors/500.blade.php`
  - `resources/views/errors/503.blade.php`

- **Inertia/Vue Pages** (for SPA navigation):
  - `resources/js/Pages/Errors/404.vue`
  - `resources/js/Pages/Errors/Error.vue`

### Configuration
- `bootstrap/app.php` - Error handler
- `.env` - Must have `APP_DEBUG=false` in production

## Why Both Formats Are Needed

In production Laravel applications using Inertia:

1. **Blade views** handle:
   - Direct URL access
   - Search engine crawlers
   - Bookmarked pages
   - External links
   - First page load

2. **Inertia views** handle:
   - Navigation within the Vue SPA
   - Client-side routing
   - AJAX requests from Vue components

## Troubleshooting

### If Error Pages Still Don't Show:

1. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Check Nginx error logs:**
   ```bash
   sudo tail -f /var/log/nginx/error.log
   ```

3. **Verify Nginx doesn't intercept errors:**
   Check `/etc/nginx/sites-available/your-site` doesn't have:
   ```nginx
   # Remove or comment out if present:
   # error_page 404 /404.html;
   # error_page 500 502 503 504 /50x.html;
   ```
   
   Nginx should pass errors to Laravel:
   ```nginx
   # Correct configuration:
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

4. **Clear browser cache:**
   - Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)
   - Or open in incognito mode

5. **Verify assets compiled:**
   ```bash
   ls -la public/build/
   # Should see manifest.json and compiled assets
   ```

## Related Issues Fixed

This deployment also includes fixes for:
- ✅ Route name conflicts resolved (10 duplicate routes fixed)
- ✅ All module API routes prefixed with 'api.'
- ✅ Transaction export with multi-sheet analytics
- ✅ Space management future booking system
- ✅ Route caching now works without errors

## Success Criteria

- ✅ 404 pages display for non-existent URLs
- ✅ Error pages work for both direct access and SPA navigation
- ✅ No nginx default error pages appear
- ✅ Proper Laravel error pages show up
- ✅ Search engines can see proper 404 pages (Blade views)
- ✅ Users in SPA see Vue error pages (no full reload)

## Notes

- The fix maintains SEO by serving proper Blade views to crawlers
- SPA users get smooth error handling without page reloads
- Production security maintained with APP_DEBUG=false
- All error pages properly themed and branded
