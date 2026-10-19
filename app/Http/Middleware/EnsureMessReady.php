<?php

namespace App\Http\Middleware;

use App\Support\AccountSeat;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMessReady
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role === 'admin') {
            return $next($request);
        }

        $seat = AccountSeat::for($user);

        if ($seat['next'] !== 'dashboard') {
            return response()->json([
                'message' => $seat['next'] === 'mess_setup'
                    ? 'Save the mess details before using the house.'
                    : 'An active subscription is required.',
                'seat' => $seat,
            ], 403);
        }

        return $next($request);
    }
}
