<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Batches\BuildRoleOverview;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Show the signed-in role's overview.
     */
    public function __invoke(#[CurrentUser] User $user, BuildRoleOverview $buildOverview): Response
    {
        return Inertia::render('Dashboard', [
            'overview' => $buildOverview->handle($user),
        ]);
    }
}
