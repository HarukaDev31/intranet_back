<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * @return void
     */
    protected function hideSensitiveRequestDetails()
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * @return void
     */
    protected function authorization()
    {
        $this->gate();

        Telescope::auth(function ($request) {
            if ($this->app->environment('local')) {
                return true;
            }

            $token = (string) config('telescope.dashboard_token', '');
            if ($token !== '') {
                $provided = (string) ($request->query('token') ?? $request->header('X-Telescope-Token', ''));
                if ($provided !== '' && hash_equals($token, $provided)) {
                    return true;
                }
            }

            $allowedIps = config('telescope.allowed_ips', []);
            if (is_array($allowedIps) && $allowedIps !== [] && in_array($request->ip(), $allowedIps, true)) {
                return true;
            }

            $user = $request->user();

            return $user !== null && Gate::check('viewTelescope', [$user]);
        });
    }

    /**
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewTelescope', function ($user) {
            $emails = array_values(array_filter(array_map('trim', explode(',', (string) env('TELESCOPE_ALLOWED_EMAILS', '')))));

            return $user !== null
                && isset($user->email)
                && $emails !== []
                && in_array($user->email, $emails, true);
        });
    }
}
